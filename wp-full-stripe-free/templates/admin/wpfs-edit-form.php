<div class="wrap">
    <?php
    /** @var \ThemeIsle\WPFullPay\Forms\FormType $formTypeInfo */

    // E.g. "inline-save-card": names the page class the admin JS looks for, and the form's partial.
    $formTypeSlug = $formTypeInfo->layout . '-' . str_replace( '_', '-', $formTypeInfo->type );
    $pageStyle = 'wpfs-page-edit-' . $formTypeSlug . '-form';
    ?>
    <div class="wpfs-page <?php echo $pageStyle; ?>">
        <?php include('partials/wpfs-header-with-client-tabs-and-back-link.php'); ?>
        <?php include('partials/wpfs-announcement.php'); ?>
        <?php include( 'partials/wpfs-edit-form-' . $formTypeSlug . '.php' ); ?>
        <div id="wpfs-success-message-container"></div>
    </div>
    <div id="wpfs-dialog-container"></div>
    <script type="text/template" id="wpfs-success-message">
        <div class="wpfs-floating-message__inner">
            <div class="wpfs-floating-message__message"><%- successMessage %></div>
            <button class="wpfs-btn wpfs-btn-icon js-hide-flash-message">
                <span class="wpfs-icon-close"></span>
            </button>
        </div>
    </script>
    <?php include( 'partials/wpfs-demo-mode.php' ); ?>
</div>
