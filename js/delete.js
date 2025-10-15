$(function(){
    var onCheck = function() {
        if ($('.checkfordelete:checked').length == 0) {
            $('#btnDeleteSelected').addClass('butActionRefused');
            $('#btnDeleteSelected').removeClass('butActionDelete');
        } else {
            $('#btnDeleteSelected').removeClass('butActionRefused');
            $('#btnDeleteSelected').addClass('butActionDelete');
        }
    };

    $("#checkall").click(function(e) {
        e.preventDefault();
        $(".checkfordelete").prop('checked', true);
        onCheck();
    });
    $("#checknone").click(function(e) {
        e.preventDefault();
        $(".checkfordelete").prop('checked', false);
        onCheck();
    });

    $('.checkfordelete').on('change', onCheck);

    $('#btnDeleteSelected').click(function(e) {
        e.preventDefault();
        if ($('.checkfordelete:checked').length > 0) {
            var input = $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', 'action')
                            .val('delete_prices');

            $('form#pricelistform')
                .append(input)
                .submit();
        }
    });
});
