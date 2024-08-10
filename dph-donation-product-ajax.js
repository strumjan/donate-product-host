const { __, _x, _n, sprintf } = wp.i18n;

function archiveCampaign(campaignId) {
if (confirm(__('Are you sure you want to archive this campaign?', 'donate-product-host'))) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'archive_campaign',
            campaign_id: campaignId,
        },
        success: function (response) {
            if (response.success) {
                alert(__('Campaign archived successfully.', 'donate-product-host'));
                window.location.href = '?page=donate-product-host&tab=archived_campaigns'; // Префрли се на табот за архивирани кампањи
            } else {
                alert(__('Failed to archive the campaign.', 'donate-product-host'));
            }
        }
    });
}
}

function unarchiveCampaign(campaignId) {
if (confirm(__('Are you sure you want to unarchive this campaign?', 'donate-product-host'))) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'unarchive_campaign',
            campaign_id: campaignId,
        },
        success: function (response) {
            if (response.success) {
                alert(__('Campaign unarchived successfully.', 'donate-product-host'));
                window.location.href = '?page=donate-product-host&tab=view_campaigns'; // Префрли се на табот за активни кампањи
            } else {
                alert(__('Failed to unarchive the campaign.', 'donate-product-host'));
            }
        },
    });
}
}
