<?php
/**
 * Confirmation form partial for deleting all patients.
 *
 * Expects the following variables in scope:
 *   - $patient_count  (int)   Total number of patients to be deleted
 *   - $csrf_token     (string) CSRF token for the form
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 */

?>
<div class="warning-banner text-center">
    <h2><?php echo xlt('⚠ WARNING: Delete All Patients'); ?></h2>
    <p class="warning-text">
        <?php echo xlt('This action will permanently delete ALL patient records from the database.'); ?>
    </p>
    <p class="warning-text">
        <?php echo xlt('This cannot be undone. Make sure you have a complete backup before proceeding.'); ?>
    </p>
    <p class="warning-text">
        <?php echo xlt('Number of patients to delete:') . ' <strong>' . text($patient_count) . '</strong>'; ?>
    </p>
</div>

<form method="post" action="delete_all_patients.php" onsubmit="return top.restoreSession()">
    <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf_token); ?>" />
    <input type="hidden" name="form_submit" value="delete_all" />

    <div class="form-group">
        <label for="confirm_text"><?php echo xlt('Type "DELETE ALL" to confirm:'); ?></label>
        <input type="text" id="confirm_text" name="confirm_text" class="form-control confirm-input" required />
    </div>

    <div class="btn-group mt-3">
        <button type="submit" class="btn btn-danger"><?php echo xla('Delete All Patients'); ?></button>
        <a href="<?php echo attr(OEGlobalsBag::getInstance()->getWebRoot()); ?>/interface/main/finder/dynamic_finder.php?csrf_token_form=<?php echo attr_url($csrf_token); ?>" class="btn btn-secondary"><?php echo xla('Cancel'); ?></a>
    </div>
</form>
