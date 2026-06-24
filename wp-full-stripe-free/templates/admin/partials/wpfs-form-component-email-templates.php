<?php
/** @var $view MM_WPFS_Admin_FormView */
/** @var $form */
/** @var $data */
?>
<div class="wpfs-form__cols wpfs-form__cols--templates">
    <div class="wpfs-form__col">
        <div class="wpfs-list-title">
            <?php esc_html_e('Available email types:', 'wp-full-stripe-free'); ?>
        </div>
        <div id="wpfs-templates-container" class="wpfs-list wpfs-list--sm wpfs-list--status"></div>
    </div>
    <div class="wpfs-form__col">
        <div id="wpfs-template-details-container" class="wpfs-form-block"></div>
    </div>
</div>
<input id="<?php $view->emailTemplates()->id(); ?>" name="<?php $view->emailTemplates()->name(); ?>" value="" <?php $view->emailTemplates()->attributes(); ?>>
<script type="text/javascript">
    var wpfsEmailTemplates = <?php echo json_encode($data->emailTemplates); ?>;
</script>
<script type="text/template" id="wpfs-email-template">
    <div class="wpfs-list__text">
        <div class="wpfs-list__title"><%= typeLabel %></div>
        <div class="wpfs-status-bullet <% if (enabled === true) { %> wpfs-status-bullet--green <% } else { %> wpfs-status-bullet--red <% } %> wpfs-list__bullet">
            <% if (enabled === true) { %><?php esc_html_e('Enabled', 'wp-full-stripe-free'); ?><% } else { %><?php esc_html_e('Disabled', 'wp-full-stripe-free'); ?><% } %>
        </div>
    </div>
</script>
<script type="text/template" id="wpfs-email-template-details">
    <div class="wpfs-form-block__title"><%= typeLabel %></div>
    <div>
        <p><%= typeDescription %></p>
    </div>
    <div class="wpfs-form-group">
        <label class="wpfs-toggler">
            <span><?php esc_html_e('Disabled', 'wp-full-stripe-free'); ?></span>
            <input type="checkbox" id="wpfs-send-email-toggle" name="wpfs-send-email-toggle" <% if (enabled === true) { %>checked<% } %>>
            <span class="wpfs-toggler__switcher"></span>
            <span><?php esc_html_e('Enabled', 'wp-full-stripe-free'); ?></span>
        </label>
    </div>
    <% if (editable === true) { %>
    <div class="js-form-template-content" <% if (enabled !== true) { %>style="display: none;"<% } %>>
        <div class="wpfs-form-group">
            <label for="wpfs-form-template-subject" class="wpfs-form-label wpfs-form-label--actions">
                <?php esc_html_e('Subject', 'wp-full-stripe-free'); ?>
                <div class="wpfs-form-label__actions">
                    <a class="wpfs-btn wpfs-btn-link js-insert-token-subject" href="#"><?php esc_html_e('Insert token', 'wp-full-stripe-free'); ?></a>
                </div>
            </label>
            <input id="wpfs-form-template-subject" class="wpfs-form-control js-form-template-subject js-subject-position-tracking js-token-target-subject" type="text" value="<%- subject %>" placeholder="<?php esc_attr_e('Leave empty to use the global template', 'wp-full-stripe-free'); ?>">
        </div>
        <div class="wpfs-form-group">
            <label for="wpfs-form-template-body" class="wpfs-form-label wpfs-form-label--actions">
                <?php esc_html_e('Body', 'wp-full-stripe-free'); ?>
                <div class="wpfs-form-label__actions">
                    <a class="wpfs-btn wpfs-btn-link js-insert-token-body" href="#"><?php esc_html_e('Insert token', 'wp-full-stripe-free'); ?></a>
                </div>
            </label>
            <textarea id="wpfs-form-template-body" class="wpfs-form-control js-form-template-body js-body-position-tracking js-token-target-body" rows="10" placeholder="<?php esc_attr_e('Leave empty to use the global template', 'wp-full-stripe-free'); ?>"><%- body %></textarea>
        </div>
    </div>
    <% } %>
</script>
<script type="text/template" id="wpfs-form-insert-token-dialog-tmpl">
    <div id="wpfs-insert-token-dialog" class="wpfs-dialog-content js-insert-token-dialog" title="<?php esc_attr_e('Insert token', 'wp-full-stripe-free'); ?>">
        <div class="wpfs-dialog-token-list">
            <div class="wpfs-form-group">
                <input class="wpfs-form-control js-token-autocomplete" type="text" placeholder="<?php esc_attr_e('Search token', 'wp-full-stripe-free'); ?>">
            </div>
        </div>
    </div>
</script>