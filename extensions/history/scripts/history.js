    // subscription via resource context menus
    $('#subscribe_for_syncfeed').livequery('click', function() {
        var classesArray = $(this).attr('class').split(' ');        
        var url = urlBase + 'history/subscribe/';
        //alert('Subscribe for '+classesArray[0]+' in '+classesArray[1]+' under '+url);return;
        $(this).replaceWith('<span class="is-processing" style="min-height: 16px; display: block"></span>');
        $.getJSON(url, {r: classesArray[0], m: classesArray[1]}, function(data) {
            $('.contextmenu-enhanced .contextmenu').fadeOut(effectTime, function(){ $(this).remove(); })
            window.location = document.URL;
        });

        return false;
    });