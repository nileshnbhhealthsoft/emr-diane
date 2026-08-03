<?php
/* Smarty version 4.3.4, created on 2026-05-28 11:06:51
  from '/var/www/html/ehrcloud/templates/insurance_companies/general_list.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_6a18681b4a47a3_56932950',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '18c8bfcb231b93ce7f959f279b0fd7e2e8873dd4' => 
    array (
      0 => '/var/www/html/ehrcloud/templates/insurance_companies/general_list.html',
      1 => 1700108885,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6a18681b4a47a3_56932950 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/html/ehrcloud/library/smarty/plugins/function.headerTemplate.php','function'=>'smarty_function_headerTemplate',),1=>array('file'=>'/var/www/html/ehrcloud/library/smarty/plugins/function.xlt.php','function'=>'smarty_function_xlt',),));
echo smarty_function_headerTemplate(array('assets'=>'common|datatables|datatables-colreorder|datatables-dt|datatables-bs'),$_smarty_tpl);?>

<a href="<?php echo $_smarty_tpl->tpl_vars['CURRENT_ACTION']->value;?>
action=edit" onclick="top.restoreSession()" class="btn btn-secondary btn-add"><?php echo smarty_function_xlt(array('t'=>'Add a Company'),$_smarty_tpl);?>
</a>
<div class="table-responsive pt-3">
    <table class="table table-striped" id="insurance">
      <thead>
          <tr>
              <th><?php echo smarty_function_xlt(array('t'=>'Name'),$_smarty_tpl);?>
</th>
              <th><?php echo smarty_function_xlt(array('t'=>'Address'),$_smarty_tpl);?>
</th>
              <th><?php echo smarty_function_xlt(array('t'=>'City, State, ZIP'),$_smarty_tpl);?>
</th>
              <th><?php echo smarty_function_xlt(array('t'=>'Phone'),$_smarty_tpl);?>
</th>
              <th><?php echo smarty_function_xlt(array('t'=>'Fax'),$_smarty_tpl);?>
</th>
              <th><?php echo smarty_function_xlt(array('t'=>'Payer ID'),$_smarty_tpl);?>
</th>
              <th><?php echo smarty_function_xlt(array('t'=>'Default X12 Partner'),$_smarty_tpl);?>
</th>
              <th><?php echo smarty_function_xlt(array('t'=>'Deactivated'),$_smarty_tpl);?>
</th>
          </tr>
      </thead>
      <tbody>
          <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['icompanies']->value, 'insurancecompany');
$_smarty_tpl->tpl_vars['insurancecompany']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['insurancecompany']->value) {
$_smarty_tpl->tpl_vars['insurancecompany']->do_else = false;
?>
          <tr>
              <td>
                  <a href="<?php echo $_smarty_tpl->tpl_vars['CURRENT_ACTION']->value;?>
action=edit&id=<?php echo attr_url($_smarty_tpl->tpl_vars['insurancecompany']->value->id);?>
" onclick="top.restoreSession()">
                      <?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->name);?>

                  </a>
              </td>
              <td><?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->address->line1);?>
 <?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->address->line2);?>
&nbsp;</td>
              <td><?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->address->city);?>
 <?php echo text(mb_strtoupper((string) $_smarty_tpl->tpl_vars['insurancecompany']->value->address->state ?? '', 'UTF-8'));?>
 <?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->address->zip);?>
&nbsp;</td>
              <td><?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->get_phone());?>
&nbsp;</td>
              <td><?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->get_fax());?>
&nbsp;</td>
              <td><?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->cms_id);?>
&nbsp;</td>
              <td><?php echo text($_smarty_tpl->tpl_vars['insurancecompany']->value->get_x12_default_partner_name());?>
&nbsp;</td>
              <td><?php if ($_smarty_tpl->tpl_vars['insurancecompany']->value->get_inactive() == 1) {
echo smarty_function_xlt(array('t'=>'Yes'),$_smarty_tpl);
}?>&nbsp;</td>
          </tr>
          <?php
}
if ($_smarty_tpl->tpl_vars['insurancecompany']->do_else) {
?>
          <tr>
              <td><?php echo smarty_function_xlt(array('t'=>'No Insurance Companies Found'),$_smarty_tpl);?>
</td>
              <!-- DataTables requires the number of cols in the table header match the table body,
                  https://datatables.net/manual/tech-notes/18 -->
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
          </tr>
          <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
      </tbody>
  </table>
</div>
<?php echo '<script'; ?>
>
$(document).ready(function () {
    $('#insurance').DataTable();
});
<?php echo '</script'; ?>
>
<?php }
}
