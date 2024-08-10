function archiveCampaign(campaignId) {
if (confirm('Are you sure you want to archive this campaign?')) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'archive_campaign',
            campaign_id: campaignId,
        },
        success: function (response) {
            if (response.success) {
                alert('Campaign archived successfully.');
                window.location.href = '?page=donate-product-host&tab=archived_campaigns'; // Префрли се на табот за архивирани кампањи
            } else {
                alert('Failed to archive the campaign.');
            }
        }
    });
}
}

function unarchiveCampaign(campaignId) {
if (confirm('Are you sure you want to unarchive this campaign?')) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'unarchive_campaign',
            campaign_id: campaignId,
        },
        success: function (response) {
            console.log(response);
            if (response.success) {
                alert('Campaign unarchived successfully.');
                window.location.href = '?page=donate-product-host&tab=view_campaigns'; // Префрли се на табот за активни кампањи
            } else {
                alert('Failed to unarchive the campaign.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log('AJAX Error:', textStatus, errorThrown);
        },
        complete: function (xhr, status) {
            console.log('AJAX request completed. Status:', status);
        }
    });
}
}
