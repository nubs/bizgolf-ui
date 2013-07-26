(function($) {
  $('pre').each(function() {
    if ($(this).text().split("\n").length > 15) {
      $(this).attr('data-contents', $(this).html()).text('- Click to show -').one('click', function() {
        $(this).html($(this).attr('data-contents')).removeAttr('data-contents');
      });
    }
  });
})(jQuery);
