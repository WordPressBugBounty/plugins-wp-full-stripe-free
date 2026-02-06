<?php
/** @var $pageTitle */
/** @var $createButtonUrl */
/** @var $createButtonLabel */
?>
<?php include('wpfs-admin-nav.php'); ?>
<div class="wpfs-page-header">
    <div id="tsdk_banner"></div>
    <div class="wpfs-page-header__headline-with-actions">
        <div class="wpfs-page-header__headline">
            <div class="wpfs-page-header__title"><?php echo $pageTitle; ?></div>
            <a class="wpfs-btn wpfs-btn-primary" href="<?php echo $createButtonUrl; ?>"><?php echo $createButtonLabel; ?></a>
        </div>
        <div class="wpfs-page-header__actions">
            <span id="wpfs-unsaved-changes-indicator"></span>
        </div>
    </div>
</div>
