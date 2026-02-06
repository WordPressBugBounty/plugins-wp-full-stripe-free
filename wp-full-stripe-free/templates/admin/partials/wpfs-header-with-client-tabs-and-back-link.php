<?php
/** @var $backLinkUrl */
/** @var $backLinkLabel */
/** @var $pageTitle */
?>
<?php include('wpfs-admin-nav.php'); ?>
<div class="wpfs-page-header wpfs-page-header--tabs">
    <div class="wpfs-page-header__back-button-wrapper">
        <a class="wpfs-page-header__back-button" href="<?php echo $backLinkUrl; ?>"><?php echo $backLinkLabel; ?></a>
    </div>
    <div id="tsdk_banner"></div>
    <div class="wpfs-page-header__headline-with-actions">
        <div class="wpfs-page-header__headline">
            <div class="wpfs-page-header__title"><?php echo $pageTitle ?></div>
        </div>
        <div class="wpfs-page-header__actions">
            <span id="wpfs-unsaved-changes-indicator"></span>
            <?php include('wpfs-header-block-preview.php'); ?>
        </div>
    </div>
    <?php include('wpfs-header-block-client-tabs.php'); ?>
</div>
