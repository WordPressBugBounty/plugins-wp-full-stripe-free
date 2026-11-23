<?php
use ThemeIsle\WPFullPay\Admin\Admin;
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Tables\OneTimePaymentsTable;
use ThemeIsle\WPFullPay\Utils;

    /** @var $pageUrl */
    /** @var $textFilter */
    /** @var $statusFilter */
    /** @var $modeFilter */
?>
<form name="wpfs-search-one-time-payments" action="<?php echo esc_url( $pageUrl ); ?>" method="post">
    <div class="wpfs-page-controls">
        <div class="wpfs-form-search wpfs-page-controls__control wpfs-page-controls__control--w320 js-form-search">
            <input class="wpfs-form-control wpfs-form-search__input" type="text" name="<?php echo Menu::PARAM_NAME_PAYMENTS_TEXT_FILTER ?>" value="<?php echo !empty( $textFilter ) ? esc_attr( $textFilter ) : "";  ?>" placeholder="<?php esc_html_e( 'Search...', 'wp-full-stripe-free'); ?>">
            <button class="wpfs-form-search__btn">
                <span class="wpfs-icon-search"></span>
            </button>
        </div>
        <div class="wpfs-ui wpfs-form-select wpfs-page-controls__control wpfs-page-controls__control--w200">
            <select class="js-selectmenu js-one-time-payments-status-filter" name="<?php echo Menu::PARAM_NAME_PAYMENTS_STATUS_FILTER ?>" id="status" data-selectmenu-prefix="<?php echo esc_attr( __( 'Status:', 'wp-full-stripe-free' ) . ' ' ); ?>">
                <option value="all" <?php echo $statusFilter === Menu::PARAM_VALUE_PAYMENT_STATUS_ALL ? 'selected': ''; ?>><?php esc_html_e( 'All', 'wp-full-stripe-free'); ?></option>
                <?php
                foreach (Utils::getPaymentStatuses() as $paymentStatus ) {
                ?>
                <option value="<?php echo esc_attr( $paymentStatus ); ?>" <?php echo $statusFilter === $paymentStatus ? 'selected': ''; ?>><?php echo esc_html( Admin::getPaymentStatusLabel( $paymentStatus) ); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="wpfs-ui wpfs-form-select wpfs-page-controls__control wpfs-page-controls__control--w200">
            <select class="js-selectmenu js-one-time-payments-mode-filter" name="<?php echo Menu::PARAM_NAME_PAYMENTS_MODE_FILTER ?>" id="mode" data-selectmenu-prefix="<?php echo esc_attr( __( 'Mode:', 'wp-full-stripe-free' ) . ' ' ); ?>">
                <option value="<?php echo Menu::PARAM_VALUE_API_MODE_ALL; ?>" <?php echo $modeFilter === Menu::PARAM_VALUE_API_MODE_ALL ? "selected" : "";  ?>><?php esc_html_e( 'All', 'wp-full-stripe-free' ); ?></option>
                <option value="<?php echo Menu::PARAM_VALUE_API_MODE_LIVE; ?>" <?php echo $modeFilter === Menu::PARAM_VALUE_API_MODE_LIVE ? "selected" : "";  ?>><?php esc_html_e( 'Live', 'wp-full-stripe-free' ); ?></option>
                <option value="<?php echo Menu::PARAM_VALUE_API_MODE_TEST; ?>" <?php echo $modeFilter === Menu::PARAM_VALUE_API_MODE_TEST ? "selected" : "";  ?>><?php esc_html_e( 'Test', 'wp-full-stripe-free' ); ?></option>
            </select>
        </div>
    </div>
</form>
<?php
    /** @var OneTimePaymentsTable $paymentsTable */
    $paymentsTable->display();
?>
<div id="payment-details-container"></div>
<script type="text/template" id="wpfs-side-pane-payment-details">
    <div class="wpfs-side-pane" data-db-id="<%- id %>" data-stripe-id="<%- paymentId %>">
        <div class="wpfs-side-pane__head">
            <div class="wpfs-side-pane__title"><?php esc_html_e( 'Payment details', 'wp-full-stripe-free' ); ?></div>
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
                <% if ( paymentStatus == 'authorized' ) { %>
                <a class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-capture-payment-details" data-tooltip-content="capture-tooltip">
                    <span class="wpfs-icon-card"></span>
                </a>
                <div class="wpfs-tooltip-content" data-tooltip-id="capture-tooltip">
                    <div class="wpfs-info-tooltip"><?php esc_html_e( 'Capture', 'wp-full-stripe-free' ); ?></div>
                </div>
                <% } %>

                <% if ( paymentStatus == 'authorized' || paymentStatus == 'paid' ) { %>
                <a class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-refund-payment-details" data-tooltip-content="refund-tooltip">
                    <span class="wpfs-icon-sync"></span>
                </a>
                <div class="wpfs-tooltip-content" data-tooltip-id="refund-tooltip">
                    <div class="wpfs-info-tooltip"><?php esc_html_e( 'Refund', 'wp-full-stripe-free' ); ?></div>
                </div>
                <% } %>

                <a class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-delete-payment-details" data-tooltip-content="remove-payment-tooltip">
                    <span class="wpfs-icon-trash"></span>
                </a>
                <div class="wpfs-tooltip-content" data-tooltip-id="remove-payment-tooltip">
                    <div class="wpfs-info-tooltip"><?php esc_html_e( 'Delete payment', 'wp-full-stripe-free' ); ?></div>
                </div>
            </div>
        </div>
        <div class="wpfs-side-pane-list">
            <div class="wpfs-side-pane-list__title"><?php esc_html_e( 'Payment', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Id:', 'wp-full-stripe-free' ); ?> <a class="wpfs-btn wpfs-btn-link" href="<%- paymentUrl %>" target="_blank"><%- paymentId %></a>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Date:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- date %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Form:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- formDisplayName %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Status:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- localizedPaymentStatus %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Mode:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- localizedApiMode %></span>
                </li>
                <% if ( coupon != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Coupon:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- coupon %></span>
                </li>
                <% } %>
            </ul>

            <div class="wpfs-side-pane-list__title"><?php esc_html_e( 'Customer', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Id:', 'wp-full-stripe-free' ); ?> <a class="wpfs-btn wpfs-btn-link" href="<%- customerUrl %>" target="_blank"><%- customerId %></a>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Name:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- customerName %></span>
                </li>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'E-mail address:', 'wp-full-stripe-free' ); ?> <a class="wpfs-btn wpfs-btn-link" href="mailto:<%- customerEmail %>"><%- customerEmail %></a>
                </li>
                <% if ( customerPhone != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Phone number:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- customerPhone %></span>
                </li>
                <% } %>
                <% if ( ipAddress != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'IP address:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- ipAddress %></span>
                </li>
                <% } %>
                <% if ( termsOfUseAcceptedAt != null ) { %>
                <li class="wpfs-side-pane-list__item">
                    <?php esc_html_e( 'Terms of use accepted:', 'wp-full-stripe-free' ); ?> <span class="wpfs-side-pane-list__highlight"><%- termsOfUseAcceptedAt %></span>
                </li>
                <% } %>
            </ul>

            <% if ( customFields != null && customFields.length > 0 ) { %>
            <div class="wpfs-side-pane-list__title"><?php esc_html_e( 'Custom fields', 'wp-full-stripe-free' ); ?></div>
            <ul class="wpfs-side-pane-list__list">
                <% _.each(customFields, function(customField) { %>
                <% if ( customField.type === 'html' ) { return; } %>
                <li class="wpfs-side-pane-list__item">
                    <%- customField.label %>: <span class="wpfs-side-pane-list__highlight"><%
                        var dv = ( customField.displayValue !== undefined && customField.displayValue !== null ) ? customField.displayValue : customField.value;
                        if ( customField.type === 'checkbox' ) { dv = ( customField.value === 'yes' ) ? '<?php echo esc_html__( 'Yes', 'wp-full-stripe-free' ); ?>' : '<?php echo esc_html__( 'No', 'wp-full-stripe-free' ); ?>'; }
                        else if ( Array.isArray( dv ) ) { dv = dv.join( ', ' ); }
                    %><%- dv %></span>
                </li>
                <% }); %>
            </ul>
            <% } %>

            <% if ( billingAddressCountry != null && billingAddressLine1 != null ) { %>
            <div class="wpfs-side-pane-list__title"><?php esc_html_e( 'Billing address', 'wp-full-stripe-free' ); ?></div>
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
            <div class="wpfs-side-pane-list__title"><?php esc_html_e( 'Shipping address', 'wp-full-stripe-free' ); ?></div>
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

