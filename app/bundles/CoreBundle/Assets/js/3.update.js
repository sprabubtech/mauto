
/**
 * Executes the first step in the update cycle
 *
 * @param container
 * @param step
 * @param state
 */
Le.processUpdate = function (container, step, state) {
    alert("Not Supported");
    // Edge case but do it anyway, remove the /index_dev.php from leBaseUrl to make sure we can always correctly call the standalone upgrader
    // var baseUrl = leBasePath + '/';
    //
    // switch (step) {
    //     // Set the update page layout
    //     case 1:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: leAjaxUrl + '?action=core:updateSetUpdateLayout',
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.success) {
    //                     mQuery('div[id=' + container + ']').html(response.content);
    //                     Le.processUpdate(container, step + 1, state);
    //                 } else if (response.redirect) {
    //                     window.location = response.redirect;
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    //
    //     // Download the update package
    //     case 2:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: leAjaxUrl + '?action=core:updateDownloadPackage',
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     mQuery('td[id=update-step-downloading-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //
    //                     if (response.success) {
    //                         mQuery('td[id=update-step-downloading-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-check text-success'));
    //                         mQuery('#updateTable tbody').append('<tr><td>' + response.nextStep + '</td><td id="update-step-extracting-status"><span class="hidden-xs">' + response.nextStepStatus + '</span><i class="pull-right fa fa-spinner fa-spin"></i></td></tr>');
    //                         Le.processUpdate(container, step + 1, state);
    //                     } else {
    //                         mQuery('td[id=update-step-downloading-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    //
    //     // Extract the update package
    //     case 3:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: leAjaxUrl + '?action=core:updateExtractPackage',
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     mQuery('td[id=update-step-extracting-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //
    //                     if (response.success) {
    //                         mQuery('td[id=update-step-extracting-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-check text-success'));
    //                         mQuery('#updateTable tbody').append('<tr><td>' + response.nextStep + '</td><td id="update-step-moving-status"><span class="hidden-xs">' + response.nextStepStatus + '</span><i class="pull-right fa fa-spinner fa-spin"></i></td></tr>');
    //                         Le.processUpdate(container, step + 1, state);
    //                     } else {
    //                         mQuery('td[id=update-step-extracting-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    //
    //     // Move the updated bundles into production
    //     case 4:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: baseUrl + 'upgrade/upgrade.php?task=moveBundles&updateState=' + state,
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     mQuery('td[id=update-step-moving-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //
    //                     if (response.error) {
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //                         // If an error state, we cannot move on
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     } else if (response.complete) {
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-spinner fa-spin'));
    //
    //                         // If complete then we go into the next step
    //                         Le.processUpdate(container, step + 1, response.updateState);
    //                     } else {
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-spinner fa-spin'));
    //
    //                         // In this section, the step hasn't completed yet so we repeat it
    //                         Le.processUpdate(container, step, response.updateState);
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    //
    //     // Move the rest of core into production
    //     case 5:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: baseUrl + 'upgrade/upgrade.php?task=moveCore&updateState=' + state,
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     mQuery('td[id=update-step-moving-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //
    //                     if (response.error) {
    //                         // If an error state, we cannot move on
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     } else if (response.complete) {
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-spinner fa-spin'));
    //
    //                         // If complete then we go into the next step
    //                         Le.processUpdate(container, step + 1, response.updateState);
    //                     } else {
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-spinner fa-spin'));
    //
    //                         // In this section, the step hasn't completed yet so we repeat it
    //                         Le.processUpdate(container, step, response.updateState);
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    //
    //     // Move the vendors into production
    //     case 6:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: baseUrl + 'upgrade/upgrade.php?task=moveVendors&updateState=' + state,
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     mQuery('td[id=update-step-moving-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //
    //                     if (response.error) {
    //                         // If an error state, we cannot move on
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     } else if (response.complete) {
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-check text-success'));
    //
    //                         // If complete then we go into the next step
    //                         mQuery('#updateTable tbody').append('<tr><td>' + response.nextStep + '</td><td id="update-step-cache-status"><span class="hidden-xs">' + response.nextStepStatus + '</span><i class="pull-right fa fa-spinner fa-spin"></i></td></tr>');
    //                         Le.processUpdate(container, step + 1, response.updateState);
    //                     } else {
    //                         // In this section, the step hasn't completed yet so we repeat it
    //                         mQuery('td[id=update-step-moving-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-spinner fa-spin'));
    //                         Le.processUpdate(container, step, response.updateState);
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    //
    //     // Clear the application cache
    //     case 7:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: baseUrl + 'upgrade/upgrade.php?task=clearCache&updateState=' + state,
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     mQuery('td[id=update-step-cache-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //
    //                     if (response.error) {
    //                         mQuery('td[id=update-step-cache-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //
    //                         // If an error state, we cannot move on
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     } else if (response.complete) {
    //                         mQuery('td[id=update-step-cache-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-check text-success'));
    //
    //                         // If complete then we go into the next step
    //                         mQuery('#updateTable tbody').append('<tr><td>' + response.nextStep + '</td><td id="update-step-database-status"><span class="hidden-xs">' + response.nextStepStatus + '</span><i class="pull-right fa fa-spinner fa-spin"></i></td></tr>');
    //                         Le.processUpdate(container, step + 1, response.updateState);
    //                     } else {
    //                         mQuery('td[id=update-step-cache-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-spinner fa-spin'));
    //
    //                         // In this section, the step hasn't completed yet so we repeat it
    //                         Le.processUpdate(container, step, response.updateState);
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    //
    //     // Migrate the database
    //     case 8:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: leAjaxUrl + '?action=core:updateDatabaseMigration&finalize=1',
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     mQuery('td[id=update-step-database-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //
    //                     if (response.success) {
    //                         mQuery('td[id=update-step-database-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-check text-success'));
    //
    //                         // If complete then we go into the next step
    //                         mQuery('#updateTable tbody').append('<tr><td>' + response.nextStep + '</td><td id="update-step-finalization-status"><span class="hidden-xs">' + response.nextStepStatus + '</span><i class="pull-right fa fa-spinner fa-spin"></i></td></tr>');
    //                         Le.processUpdate(container, step + 1, state);
    //                     } else {
    //                         mQuery('td[id=update-step-database-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //
    //                         // If an error state, we cannot move on
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 // Redirect to the update/schema page in a last ditch attempt instead of just failing
    //                 window.location = leBaseUrl + '/app/update/schema?update=1';
    //             }
    //         });
    //         break;
    //
    //     // Finalize update
    //     case 9:
    //         mQuery.ajax({
    //             showLoadingBar: true,
    //             url: leAjaxUrl + '?action=core:updateFinalization',
    //             dataType: 'json',
    //             success: function (response) {
    //                 if (response.redirect) {
    //                     window.location = response.redirect;
    //                 } else {
    //                     if (response.success) {
    //                         mQuery('div[id=' + container + ']').html('<div class="alert alert-le">' + response.message + '</div>');
    //
    //                         if (response.postmessage) {
    //                             mQuery('<div>'+response.postmessage+'</div>').insertAfter('div[id=' + container + '] .alert');
    //                         }
    //                     } else {
    //                         mQuery('td[id=update-step-finalization-status]').html('<span class="hidden-xs">' + response.stepStatus + '</span>');
    //                         mQuery('td[id=update-step-finalization-status]').append(mQuery('<i></i>').addClass('pull-right fa fa-warning text-danger'));
    //                         mQuery('div[id=main-update-panel]').removeClass('panel-default').addClass('panel-danger');
    //                         mQuery('div#main-update-panel div.panel-body').prepend('<div class="alert alert-danger">' + response.message + '</div>');
    //                     }
    //                 }
    //             },
    //             error: function (request, textStatus, errorThrown) {
    //                 Le.processAjaxError(request, textStatus, errorThrown);
    //             }
    //         });
    //         break;
    // }
    //
    // Le.stopPageLoadingBar();
};
