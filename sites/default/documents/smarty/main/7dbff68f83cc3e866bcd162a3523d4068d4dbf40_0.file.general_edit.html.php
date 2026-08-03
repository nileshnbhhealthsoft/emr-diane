<?php
/* Smarty version 4.3.4, created on 2026-05-29 10:31:37
  from '/var/www/html/ehrcloud/templates/pharmacies/general_edit.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_6a19b1599fb246_58627337',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7dbff68f83cc3e866bcd162a3523d4068d4dbf40' => 
    array (
      0 => '/var/www/html/ehrcloud/templates/pharmacies/general_edit.html',
      1 => 1700108885,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6a19b1599fb246_58627337 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/html/ehrcloud/library/smarty/plugins/function.xlt.php','function'=>'smarty_function_xlt',),1=>array('file'=>'/var/www/html/ehrcloud/vendor/smarty/smarty/libs/plugins/function.html_options.php','function'=>'smarty_function_html_options',),));
?>
<form name="pharmacy" method="post" action="<?php echo $_smarty_tpl->tpl_vars['FORM_ACTION']->value;?>
"  class='form-horizontal' onsubmit="return top.restoreSession()">
<!-- it is important that the hidden form_id field be listed first, when it is called is populates any old information attached with the id, this allows for partial edits
        if it were called last, the settings from the form would be overwritten with the old information-->
    <input type="hidden" name="form_id" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->id);?>
" />
    <div class="form-row py-sm-2">
        <label for="name" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Name'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="name" name="name" class="form-control" aria-describedby="nameHelpBox" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->name);?>
" onKeyDown="PreventIt(event)" />
            <span id="nameHelpBox" class="help-block">(<?php echo smarty_function_xlt(array('t'=>'Required'),$_smarty_tpl);?>
)</span>
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="address_line1" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Address'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="address_line1" name="address_line1" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->address->line1);?>
" onKeyDown="PreventIt(event)">
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="address_line2" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Address'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="address_line2" name="address_line2" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->address->line2);?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="city" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'City'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="city" name="city" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->address->city);?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="state" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'State'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" maxlength="2" id="state" name="state" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->address->state);?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="city" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Zip Code'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="zip" name="zip" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->address->zip);?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="email" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Email'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="email" name="email" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->email);?>
" onKeyDown="PreventIt(event)">
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="phone" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Phone'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->get_phone());?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="fax" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Fax'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="fax" name="fax" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->get_fax());?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="npi" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'NPI'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="npi" name="npi" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->get_npi());?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="ncpdp" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'NCPDP'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <input type="text" id="ncpdp" name="ncpdp" class="form-control" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->get_ncpdp());?>
" onKeyDown="PreventIt(event)" />
        </div>
    </div>
    <div class="form-row py-sm-2">
        <label for="transmit_method" class="col-form-label col-sm-2"><?php echo smarty_function_xlt(array('t'=>'Default Method'),$_smarty_tpl);?>
</label>
        <div class="col-sm-8">
            <select id="transmit_method" name="transmit_method" class="form-control">
                <?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['pharmacy']->value->transmit_method_array,'selected'=>$_smarty_tpl->tpl_vars['pharmacy']->value->transmit_method),$_smarty_tpl);?>

            </select>
        </div>
    </div>
    <div class="btn-group offset-sm-2">
        <a href="javascript:submit_pharmacy();" class="btn btn-secondary btn-save" onclick="top.restoreSession()">
            <?php echo smarty_function_xlt(array('t'=>'Save'),$_smarty_tpl);?>

        </a>
        <a href="controller.php?practice_settings&pharmacy&action=list" class="btn btn-link btn-cancel" onclick="top.restoreSession()">
            <?php echo smarty_function_xlt(array('t'=>'Cancel'),$_smarty_tpl);?>

        </a>
    </div>
    <input type="hidden" name="id" value="<?php echo attr($_smarty_tpl->tpl_vars['pharmacy']->value->id);?>
" />
    <input type="hidden" name="process" value="<?php echo attr($_smarty_tpl->tpl_vars['PROCESS']->value);?>
" />
</form>

<?php echo '<script'; ?>
>
function submit_pharmacy()
{
    if(document.pharmacy.name.value.length>0)
    {
        top.restoreSession();
        document.pharmacy.submit();
        //Z&H Removed redirection
    }
    else
    {
        document.pharmacy.name.style.backgroundColor="red";
        document.pharmacy.name.focus();
    }
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
<?php }
}
