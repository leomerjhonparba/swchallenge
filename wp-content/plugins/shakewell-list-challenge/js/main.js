(function($){$(function(){

    jQuery(document).ready(function () {
        const list = jQuery('#my-list');
    
        list.sortable();
    
        jQuery('#add-item').on('click', function () {
            const newItem = jQuery('#new-item').val().trim();
            if (newItem) {
                list.append(`<li class="list-item">${newItem}<button class="remove-item">&times;</button></li>`);
                jQuery('#new-item').val('');
            }
        });
    
        list.on('click', '.remove-item', function () {
            $(this).closest('li').remove();
        });
    
        jQuery('#save-list').on('click', function () {
            const items = [];
            list.find('.list-item').each(function () {
                items.push($(this).text().replace('×', '').trim());
            });
    
            $.post(MyListChallengeAjax.ajax_url, {
                action: 'update_my_list',
                items: items,
                nonce: MyListChallengeAjax.nonce
            }, function (response) {
                if (response.success) {
                    alert('List updated successfully!');
                } else {
                    alert('Error updating list.');
                }
            });
        });
    });

})})(jQuery)