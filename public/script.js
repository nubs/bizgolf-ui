(function($) {
  $('pre').each(function() {
    var contents = $(this).html();
    if (contents.length > 500) {
      $(this).attr('data-contents', contents).text('- Click to show -').one('click', function() {
        $(this).html($(this).attr('data-contents')).removeAttr('data-contents');
      });
    }
  });
})(jQuery);
