<?php
/** @var $pageUrl */
/** @var $textFilter */
/** @var $modeFilter */
?>
<form name="wpfs-search-donations" action="<?php echo $pageUrl; ?>" method="post">
    <div class="wpfs-page-controls">
        <div class="wpfs-form-search wpfs-page-controls__control wpfs-page-controls__control--w320 js-form-search">
            <input class="wpfs-form-control wpfs-form-search__input" type="text" name="<?php echo MM_WPFS_Admin_Menu::PARAM_NAME_DONATIONS_TEXT_FILTER ?>" value="<?php echo !empty( $textFilter ) ? $textFilter : "";  ?>" placeholder="<?php esc_html_e( 'Search...', 'wp-full-stripe-free'); ?>">
            <button class="wpfs-form-search__btn">
                <span class="wpfs-icon-search"></span>
            </button>
        </div>
        <div class="wpfs-ui wpfs-form-select wpfs-page-controls__control wpfs-page-controls__control--w200">
            <select class="js-selectmenu js-donations-mode-filter" name="<?php echo MM_WPFS_Admin_Menu::PARAM_NAME_DONATIONS_MODE_FILTER ?>" id="mode" data-selectmenu-prefix="<?php _e( "Mode: ", 'wp-full-stripe-free' ); ?>">
                <option value="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_API_MODE_ALL; ?>" <?php echo $modeFilter === MM_WPFS_Admin_Menu::PARAM_VALUE_API_MODE_ALL ? "selected" : "";  ?>><?php _e( 'All', 'wp-full-stripe-free' ); ?></option>
                <option value="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_API_MODE_LIVE; ?>" <?php echo $modeFilter === MM_WPFS_Admin_Menu::PARAM_VALUE_API_MODE_LIVE ? "selected" : "";  ?>><?php _e( 'Live', 'wp-full-stripe-free' ); ?></option>
                <option value="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_API_MODE_TEST; ?>" <?php echo $modeFilter === MM_WPFS_Admin_Menu::PARAM_VALUE_API_MODE_TEST ? "selected" : "";  ?>><?php _e( 'Test', 'wp-full-stripe-free' ); ?></option>
            </select>
        </div>
    </div>
</form>
<?php
/** @var WPFS_Donations_Table $donationsTable */
$donationsTable->display();
?>
<div id="donation-details-container"></div>
<script type="text/template" id="wpfs-side-pane-donation-details">
    <div class="wpfs-side-pane" data-db-id="<%- id %>" data-stripe-id="<%- paymentIntentId %>">
        <div class="wpfs-side-pane__head">
            <div class="wpfs-side-pane__title"><?php _e( 'Donation details', 'wp-full-stripe-free' ); ?></div>
            <button class="wpfs-btn wpfs-btn-icon wpfs-side-pane__close js-close-side-pane">
                <span class="wpfs-icon-close"></span>
            </button>
        </div>
        <div class="wpfs-side-pane-item">
            <span class="<%- paymentMethodCssClass %> wpfs-side-pane-item__icon"></span>
            <div class="wpfs-side-pane-item__data">
                <div class="wpfs-side-pane-item__name"><%- paymentMethodTooltip %></div>
                <div class="wpfs-side-pane-item__price"><%- localizedAmount %></div>
            </div>
            <div class="wpfs-side-pane-item__actions">
                <% if ( paymentStatus == 'paid' ) { %>
                <a class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-refund-donation-details" data-tooltip-content="refund-donation-tooltip">
                    <span class="wpfs-icon-sync"></span>
                </a>
                <div class="wpfs-tooltip-content" data-tooltip-id="refund-donation-tooltip">
                    <div class="wpfs-info-tooltip"><?php _e( 'Refund', 'wp-full-stripe-free' ); ?></div>
                </div>
                <% } %>

                <% if ( donationStatus == 'running' ) { %>
                <a class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-cancel-donation-details" data-tooltip-content="cancel-donation-tooltip">
                    <span class="wpfs-icon-cancel"></span>
                </a>
                <div class="wpfs-tooltip-content" data-tooltip-id="cancel-donation-tooltip">
                    <div class="wpfs-info-tooltip"><?php _e( 'Cancel', 'wp-full-stripe-free' ); ?></div>
                </div>
                <% } %>

                <a class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-delete-donation-details" data-tooltip-content="delete-donation-tooltip">
                    <span class="wpfs-icon-trash"></span>
                </a>
                <div class="wpfs-tooltip-content" data-tooltip-id="delete-donation-tooltip">
                    <div class="wpfs-info-tooltip"><?php _e( 'Delete donation', 'wp-full-stripe-free' ); ?></div>
                </div>
            </div>
        </div>
        <div class="wpfs-side-pane-list">
            <div class="wpfs-side-pane-list__title"><?php _e( 'Donation', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Donation id:', 'wp-full-stripe-free' ); ?> <a class="wpfs-btn wpfs-btn-link" href="<%- paymentIntentUrl %>" target="_blank"><%- paymentIntentId %></a>
                </li>
                <% if ( subscriptionId != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Recurring id:', 'wp-full-stripe-free' ); ?> <a class="wpfs-btn wpfs-btn-link" href="<%- subscriptionUrl %>" target="_blank"><%- subscriptionId %></a>
                </li>
                <% } %>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Date:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- date %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Form:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- formDisplayName %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Status:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- localizedDonationStatus %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Mode:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- localizedApiMode %></span>
                </li>
            </ul>

            <div class="wpfs-side-pane-list__title"><?php _e( 'Donor', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Id:', 'wp-full-stripe-free' ); ?> <a class="wpfs-btn wpfs-btn-link" href="<%- customerUrl %>" target="_blank"><%- customerId %></a>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Name:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- customerName %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'E-mail address:', 'wp-full-stripe-free' ); ?> <a class="wpfs-btn wpfs-btn-link" href="mailto:<%- customerEmail %>"><%- customerEmail %></a>
                </li>
                <% if ( customerPhone != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'Phone number:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- customerPhone %></span>
                </li>
                <% } %>
                <% if ( ipAddress != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <?php _e( 'IP address:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- ipAddress %></span>
                </li>
                <% } %>
            </ul>

            <%  if ( customFields != null && customFields.length > 0 ) { %>
            <div class="wpfs-side-pane-list__title"><?php _e( 'Custom fields', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <% _.each(customFields, function(customField) { %>
                <li class="wpfs-side-pane-list__item">
                    <%- customField.label %>: <span class="wpfs-side-pane-list__highlight"><%- customField.value %></span>
                </li>
                <% }); %>
            </ul>
            <% } %>

            <% if ( billingAddressCountry != null && billingAddressLine1 != null ) { %>
            <div class="wpfs-side-pane-list__title"><?php _e( 'Billing address', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <% if ( billingName != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- billingName %></span>
                </li>
                <% } %>
                <% if ( billingAddressLine1 != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- billingAddressLine1 %><% if ( billingAddressLine1 != null ) { %>, <%- billingAddressLine2 %><% } %></span>
                </li>
                <% } %>
                <% if ( billingAddressCity != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- billingAddressZip %> <%- billingAddressCity %><% if ( billingAddressState != null ) { %>, <%- billingAddressState %><% } %></span>
                </li>
                <% } %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- billingAddressCountry %></span>
                </li>
            </ul>
            <% } %>

            <% if ( shippingAddressCountry != null && shippingAddressLine1 != null ) { %>
            <div class="wpfs-side-pane-list__title"><?php _e( 'Shipping address', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <% if ( shippingName != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- shippingName %></span>
                </li>
                <% } %>
                <% if ( shippingAddressLine1 != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- shippingAddressLine1 %><% if ( shippingAddressLine1 != null ) { %>, <%- shippingAddressLine2 %><% } %></span>
                </li>
                <% } %>
                <% if ( shippingAddressCity != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- shippingAddressZip %> <%- shippingAddressCity %><% if ( shippingAddressState != null ) { %>, <%- shippingAddressState %><% } %></span>
                </li>
                <% } %>
                <li class="wpfs-side-pane-list__item">
                    <span class="wpfs-side-pane-list__highlight"><%- shippingAddressCountry %></span>
                </li>
            </ul>
            <% } %>
        </div>
    </div>
</script>

