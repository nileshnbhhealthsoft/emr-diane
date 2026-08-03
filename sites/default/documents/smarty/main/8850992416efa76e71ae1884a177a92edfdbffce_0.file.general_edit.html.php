<?php
/* Smarty version 4.3.4, created on 2026-05-29 10:29:49
  from '/var/www/html/ehrcloud/templates/insurance_numbers/general_edit.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_6a19b0edb32d54_89620644',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '8850992416efa76e71ae1884a177a92edfdbffce' => 
    array (
      0 => '/var/www/html/ehrcloud/templates/insurance_numbers/general_edit.html',
      1 => 1700108885,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6a19b0edb32d54_89620644 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/html/ehrcloud/library/smarty/plugins/function.xlt.php','function'=>'smarty_function_xlt',),1=>array('file'=>'/var/www/html/ehrcloud/vendor/smarty/smarty/libs/plugins/function.html_options.php','function'=>'smarty_function_html_options',),));
if ((isset($_smarty_tpl->tpl_vars['ERROR']->value))) {?>
    <div class="alert alert-danger"><?php echo text($_smarty_tpl->tpl_vars['ERROR']->value);?>
</div>
<?php } else { ?>
    <form name="provider" method="post" action="<?php echo $_smarty_tpl->tpl_vars['FORM_ACTION']->value;?>
" class='form-horizontal' onsubmit="return top.restoreSession()">
        <!-- it is important that the hidden form_id field be listed first, when it is called it populates any old information attached with the id, this allows for partial edits
                    if it were called last, the settings from the form would be overwritten with the old information-->
        <input type="hidden" name="form_id" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->id);?>
" />

        <table class="table table-responsive table-striped">

        <tr><td colspan="5" style="border-style:none;" class="bold">
            <?php echo text($_smarty_tpl->tpl_vars['provider']->value->get_name_display());?>

        </td></tr>

        <tr  class="showborder_head">
            <th class="small"><?php echo smarty_function_xlt(array('t'=>'Company Name'),$_smarty_tpl);?>
</th>
            <th class="small"><?php echo smarty_function_xlt(array('t'=>'Provider Number'),$_smarty_tpl);?>
</th>
            <th class="small"><?php echo smarty_function_xlt(array('t'=>'Rendering Provider Number'),$_smarty_tpl);?>
</th>
            <th class="small"><?php echo smarty_function_xlt(array('t'=>'Group Number'),$_smarty_tpl);?>
</th>
        </tr>
        <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['provider']->value->get_insurance_numbers(), 'num_set', false, NULL, 'inums', array (
));
$_smarty_tpl->tpl_vars['num_set']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['num_set']->value) {
$_smarty_tpl->tpl_vars['num_set']->do_else = false;
?>
            <tr>
                <td valign="middle">
                    <a href="<?php echo $_smarty_tpl->tpl_vars['CURRENT_ACTION']->value;?>
action=edit&id=<?php echo attr_url($_smarty_tpl->tpl_vars['num_set']->value->get_id());?>
&showform=true" onclick="top.restoreSession()">
                        <?php echo text($_smarty_tpl->tpl_vars['num_set']->value->get_insurance_company_name());?>
&nbsp;
                    </a>
                </td>
                <td><?php echo text($_smarty_tpl->tpl_vars['num_set']->value->get_provider_number());?>
&nbsp;</td>
                <td><?php echo text($_smarty_tpl->tpl_vars['num_set']->value->get_rendering_provider_number());?>
&nbsp;</td>
                <td><?php echo text($_smarty_tpl->tpl_vars['num_set']->value->get_group_number());?>
&nbsp;</td>
            </tr>
        <?php
}
if ($_smarty_tpl->tpl_vars['num_set']->do_else) {
?>
        <tr>
            <td colspan="5"><?php echo smarty_function_xlt(array('t'=>'No entries found, use the form below to add an entry.'),$_smarty_tpl);?>
</td>
        </tr>
        <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>

        <tr>
          <td style="border-style:none;" colspan="5">
            <a href="<?php echo $_smarty_tpl->tpl_vars['CURRENT_ACTION']->value;?>
action=edit&id=&provider_id=<?php echo attr_url($_smarty_tpl->tpl_vars['provider']->value->get_id());?>
&showform=true" class="btn btn-secondary btn-add" onclick="top.restoreSession()"><?php echo smarty_function_xlt(array('t'=>'Add New'),$_smarty_tpl);?>
</a>
        </td>
      </tr>
    </table>

        <?php if ($_smarty_tpl->tpl_vars['show_edit_gui']->value) {?>
            <div class="alert alert-info">
                <?php if ($_smarty_tpl->tpl_vars['ins']->value->get_id() == '') {?>
                    <?php echo smarty_function_xlt(array('t'=>'Add Provider Number'),$_smarty_tpl);?>

                <?php } else { ?>
                    <?php echo smarty_function_xlt(array('t'=>'Update Provider Number'),$_smarty_tpl);?>

                <?php }?>
            </div>
            <div class="form-row my-sm-2">
                <label for="insurance_company_id" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Insurance Company'),$_smarty_tpl);?>
</label>
                <div class="col-sm-8">
                    <?php if ($_smarty_tpl->tpl_vars['ins']->value->get_id() == '') {?>
                        <select id="insurance_company_id" name="insurance_company_id" class="form-control">
                            <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['ic_array']->value,'values'=>$_smarty_tpl->tpl_vars['ic_array']->value,'selected'=>$_smarty_tpl->tpl_vars['ins']->value->get_insurance_company_id()),$_smarty_tpl);?>

                        </select>
                    <?php } else { ?>
                        <span id="insurance_company_id" class="form-control-static">
                            <?php echo text($_smarty_tpl->tpl_vars['ins']->value->get_insurance_company_name());?>

                        </span>
                    <?php }?>
                </div>
            </div>
            <div class="form-row my-sm-2">
                <label for="provider_number" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Provider Number'),$_smarty_tpl);?>
</label>
                <div class="col-sm-8">
                    <input type="text" id="provider_number" name="provider_number" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_provider_number());?>
" onKeyDown="PreventIt(event)">
                </div>
            </div>
            <div class="form-row my-sm-2">
                <label for="provider_number_type" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Provider Number (Type)'),$_smarty_tpl);?>
</label>
                <div class="col-sm-8">
                    <select id="provider_number_type" name="provider_number_type" class="form-control">
                        <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['ic_type_options_array']->value,'values'=>$_smarty_tpl->tpl_vars['ins']->value->provider_number_type_array,'selected'=>$_smarty_tpl->tpl_vars['ins']->value->get_provider_number_type()),$_smarty_tpl);?>

                    </select>
                </div>
            </div>
            <div class="form-row my-sm-2">
                <label for="rendering_provider_number" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Rendering Provider Number'),$_smarty_tpl);?>
</label>
                <div class="col-sm-8">
                    <input type="text" id="rendering_provider_number" name="rendering_provider_number" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_rendering_provider_number());?>
" onKeyDown="PreventIt(event)">
                </div>
            </div>
            <div class="form-row my-sm-2">
                <label for="rendering_provider_number_type" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Rendering Provider Number (Type)'),$_smarty_tpl);?>
</label>
                <div class="col-sm-8">
                    <select id="rendering_provider_number_type" name="rendering_provider_number_type" class="form-control">
                        <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['ic_rendering_type_options_array']->value,'values'=>$_smarty_tpl->tpl_vars['ins']->value->rendering_provider_number_type_array,'selected'=>$_smarty_tpl->tpl_vars['ins']->value->get_rendering_provider_number_type()),$_smarty_tpl);?>

                    </select>
                </div>
            </div>
            <div class="form-row my-sm-2">
                <label for="group_number" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Group Number'),$_smarty_tpl);?>
</label>
                <div class="col-sm-8">
                    <input type="text" id="group_number" name="group_number" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_group_number());?>
" onKeyDown="PreventIt(event)" />
                </div>
            </div>
            <div class="btn-group offset-sm-2">
                <?php if ($_smarty_tpl->tpl_vars['ins']->value->get_id() == '') {?>
                    <a href="javascript:submit_insurancenumbers_add();" class="btn btn-secondary btn-save" onclick="top.restoreSession()">
                        <?php echo smarty_function_xlt(array('t'=>'Save'),$_smarty_tpl);?>

                    </a>
                <?php } else { ?>
                    <a href="javascript:submit_insurancenumbers_update();" class="btn btn-secondary btn-save" onclick="top.restoreSession()">
                        <?php echo smarty_function_xlt(array('t'=>'Save'),$_smarty_tpl);?>

                    </a>
                <?php }?>
                <a href="controller.php?practice_settings&insurance_numbers&action=list" class="btn btn-link btn-cancel" onclick="top.restoreSession()">
                    <?php echo smarty_function_xlt(array('t'=>'Cancel'),$_smarty_tpl);?>

                </a>
            </div>

        <?php } else { ?>
            <input type="hidden" name="provider_number" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_provider_number());?>
" />
            <input type="hidden" name="provider_number_type" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_provider_number_type());?>
" />
            <input type="hidden" name="rendering_provider_number" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_rendering_provider_number());?>
" />
            <input type="hidden" name="rendering_provider_number_type" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_rendering_provider_number_type());?>
" />
            <input type="hidden" name="group_number" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_group_number());?>
" />
        <?php }?>

        <input type="hidden" name="id" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->id);?>
" />
        <input type="hidden" name="provider_id" value="<?php echo attr($_smarty_tpl->tpl_vars['ins']->value->get_provider_id());?>
" />
        <input type="hidden" name="process" value="<?php echo attr($_smarty_tpl->tpl_vars['PROCESS']->value);?>
" />
    </form>
<?php }?>

<?php echo '<script'; ?>
>
function submit_insurancenumbers_update() {
    top.restoreSession();
    document.provider.submit();
}
function submit_insurancenumbers_add() {
    top.restoreSession();
    document.provider.submit();
    //Z&H Removed redirection
}

function Waittoredirect(delaymsec) {
 var st = new Date();
 var et = null;
 do {
 et = new Date();
 } while ((et - st) < delaymsec);

 }
<?php echo '</script'; ?>
>

<style>

text,select {

    font-size:9pt;
    
}

</style>
<?php }
}
