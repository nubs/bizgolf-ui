<?php
return function(MongoDB $db) {
    $collection = $db->holes;

    $fleshOutHole = function($hole, array $conditions = []) use ($collection) {
        $loadSpecification = function(array $hole) {
            if (empty($hole['fileName'])) {
                $hole['specification'] = eval(preg_replace('/^<\?(php)?/i', '', $hole['specification']));
            } else {
                $hole['specification'] = \Bizgolf\loadHole($hole['fileName']);
            }

            $trim = $hole['specification']['trim'];
            $trims = ['trim' => 'Full Trim', 'ltrim' => 'Left Trim', 'rtrim' => 'Right Trim'];
            $hole['trim'] = isset($trims[$trim]) ? $trims[$trim] : $trim;

            return $hole;
        };

        $fleshOutSubmissions = function(array $hole) use($conditions) {
            if (!array_key_exists('submissions', $hole)) {
                $hole['submissions'] = [];
            }

            $shortest = array_reduce($hole['submissions'], function($min, $submission) {
                if ($submission['result'] && ($min === null || $submission['length'] < $min['length'])) {
                    return $submission;
                }

                return $min;
            });

            foreach ($hole['submissions'] as &$submission) {
                $submission['hole'] = ['_id' => $hole['_id'], 'title' => $hole['title']];
                $submission['invertedCode'] = preg_replace_callback('/([~$]?)([^[:ascii:]]+)/', function($matches) {
                    $prefix = "~'";
                    $postfix = "'";

                    if ($matches[1] === '$') {
                        $prefix = "\${" . $prefix;
                        $postfix .= '}';
                    } elseif ($matches[1] === '~') {
                        $prefix = "'";
                    }

                    return $prefix . str_replace("'", "\\'", ~$matches[2]) . $postfix;
                }, utf8_decode($submission['code']));
                $submission['timestamp'] = $submission['_id']->getTimestamp();
                $submission['timestampFormatted'] = \Carbon\Carbon::createFromTimeStamp($submission['timestamp'])->diffForHumans();
                $submission['score'] = $submission['result'] ? (int)($shortest['length'] * 1000 / $submission['length']) : 0;
                $submission['viewableByUser'] = array_key_exists('visibleBy', $conditions) &&
                    (
                        !empty($conditions['visibleBy']['isAdmin']) ||
                        $hole['hasEnded'] ||
                        (!empty($conditions['visibleBy']['_id']) && $conditions['visibleBy']['_id'] == $submission['user']['_id'])
                    );
            }

            usort($hole['submissions'], function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            return $hole;
        };

        $loadScoreboard = function(array $hole) {
            $hole['scoreboard'] = [];
            foreach ($hole['submissions'] as $submission) {
                $userId = (string)$submission['user']['_id'];
                $isShorter = !isset($hole['scoreboard'][$userId]) || $submission['length'] < $hole['scoreboard'][$userId]['length'];
                if ($submission['result'] && $isShorter) {
                    $hole['scoreboard'][$userId] = $submission;
                }
            }

            usort($hole['scoreboard'], function($a, $b) {
                return $a['length'] - $b['length'] ?: $a['timestamp'] - $b['timestamp'];
            });

            return $hole;
        };

        $hole['hasStarted'] = empty($hole['startDate']) || $hole['startDate'] <= time();
        $hole['hasEnded'] = !empty($hole['endDate']) && $hole['endDate'] < time();
        $hole['isOpen'] = $hole['hasStarted'] && !$hole['hasEnded'];
        $hole['startDateFormatted'] = empty($hole['startDate']) ? null : date(DATE_RFC2822, $hole['startDate']);
        $hole['endDateFormatted'] = empty($hole['endDate']) ? null : date(DATE_RFC2822, $hole['endDate']);

        return $loadScoreboard($loadSpecification($fleshOutSubmissions($hole)));
    };

    $findOne = function($id, array $conditions = []) use($collection, $fleshOutHole) {
        $hole = null;
        try {
            $hole = $collection->findOne(['_id' => new MongoId($id)]);
        } catch (Exception $e) {
        }

        if ($hole === null) {
            throw new Exception("Hole '{$id}' does not exist.");
        }

        $hole = $fleshOutHole($hole, $conditions);

        if (array_key_exists('visibleBy', $conditions) && empty($conditions['visibleBy']['isAdmin']) && !$hole['hasStarted']) {
            throw new Exception('Hole has not started.');
        }

        if (array_key_exists('canSubmit', $conditions) && empty($conditions['canSubmit']['isAdmin']) && !$hole['isOpen']) {
            throw new Exception('Hole is not active.');
        }

        return $hole;
    };

    return [
        'find' => function(array $conditions = []) use($collection, $fleshOutHole) {
            $visibleBy = null;
            if (array_key_exists('visibleBy', $conditions)) {
                $visibleBy = $conditions['visibleBy'];
                unset($conditions['visibleBy']);
            }

            $holes = [];
            foreach (iterator_to_array($collection->find($conditions)) as $hole) {
                $hole = $fleshOutHole($hole, ['visibleBy' => $visibleBy]);
                if (!empty($visibleBy['isAdmin']) || $hole['hasStarted']) {
                    $holes[] = $hole;
                }
            }

            usort($holes, function($a, $b) {
                return $b['_id']->getTimestamp() - $a['_id']->getTimestamp();
            });

            return $holes;
        },
        'findOne' => $findOne,
        'addSubmission' => function($id, array $user, $submission)  use($collection, $findOne) {
            $hole = $findOne($id, ['canSubmit' => $user]);
            $result = \Bizgolf\judge($hole['specification'], 'php-5.5', $submission);
            $result['_id'] = new MongoId();
            $result['user'] = ['_id' => $user['_id'], 'username' => $user['username']];

            $code = file_get_contents($submission);
            $result['code'] = utf8_encode($code);
            $result['output'] = utf8_encode($result['output']);
            $result['stderr'] = utf8_encode($result['stderr']);
            $result['length'] = strlen($code);

            $collection->update(['_id' => new MongoId($id)], ['$push' => ['submissions' => $result]]);
        },
        'revalidateSubmissions' => function($id) use ($collection, $findOne) {
            $hole = $findOne($id);
            $changedSubmissions = [];
            foreach ($hole['submissions'] as $submission) {
                $submissionFile = tempnam(sys_get_temp_dir(), 'revalidate');
                file_put_contents($submissionFile, utf8_decode($submission['code']));
                $result = \Bizgolf\judge($hole['specification'], 'php-5.5', $submissionFile);
                if ($result['result'] !== $submission['result']) {
                    $submission['result'] = $result['result'];
                    $collection->update(
                        ['_id' => new MongoId($id), 'submissions._id' => $submission['_id']],
                        [
                            '$set' => [
                                'submissions.$.result' => $result['result'],
                                'submissions.$.output' => utf8_encode($result['output']),
                                'submissions.$.stderr' => utf8_encode($result['stderr']),
                                'submissions.$.sample' => $result['sample'],
                                'submissions.$.constantValue' => $result['constantValue'],
                            ],
                        ]
                    );
                    $changedSubmissions[] = $submission;
                }
            }

            return $changedSubmissions;
        },
        'create' => function(array $fields) use($collection) {
            $collection->insert($fields);

            return $fields;
        },
    ];
};
