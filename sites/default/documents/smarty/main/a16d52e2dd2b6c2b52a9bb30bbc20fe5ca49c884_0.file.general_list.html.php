<?php
/* Smarty version 4.3.4, created on 2026-05-29 10:29:11
  from '/var/www/html/ehrcloud/templates/insurance_numbers/general_list.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_6a19b0c7b94a91_29737486',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a16d52e2dd2b6c2b52a9bb30bbc20fe5ca49c884' => 
    array (
      0 => '/var/www/html/ehrcloud/templates/insurance_numbers/general_list.html',
      1 => 1700108885,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6a19b0c7b94a91_29737486 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/html/ehrcloud/library/smarty/plugins/function.xlt.php','function'=>'smarty_function_xlt',),));
?>
<div class="table-responsive">
  <table class="table table-striped">
      <thead>
      <tr>
          <th><?php echo smarty_function_xlt(array('t'=>'Name'),$_smarty_tpl);?>
</th>
          <th>&nbsp;</th>
          <th><?php echo smarty_function_xlt(array('t'=>'Provider'),$_smarty_tpl);?>
 #</th>
          <th><?php echo smarty_function_xlt(array('t'=>'Rendering'),$_smarty_tpl);?>
 #</th>
          <th><?php echo smarty_function_xlt(array('t'=>'Group'),$_smarty_tpl);?>
 #</th>
      </tr>
      </thead>
      <tbody>
      <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['providers']->value, 'provider');
$_smarty_tpl->tpl_vars['provider']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['provider']->value) {
$_smarty_tpl->tpl_vars['provider']->do_else = false;
?>
      <tr>
          <td>
              <a href="<?php echo $_smarty_tpl->tpl_vars['CURRENT_ACTION']->value;?>
action=edit&id=default&provider_id=<?php echo attr_url($_smarty_tpl->tpl_vars['provider']->value->id);?>
" onclick="top.restoreSession()">
                  <?php echo text($_smarty_tpl->tpl_vars['provider']->value->get_name_display());?>

              </a>
          </td>
          <td><?php echo smarty_function_xlt(array('t'=>'Default'),$_smarty_tpl);?>
&nbsp;</td>
          <td><?php echo text($_smarty_tpl->tpl_vars['provider']->value->get_provider_number_default());?>
&nbsp;</td>
          <td><?php echo text($_smarty_tpl->tpl_vars['provider']->value->get_rendering_provider_number_default());?>
&nbsp;</td>
          <td><?php echo text($_smarty_tpl->tpl_vars['provider']->value->get_group_number_default());?>
&nbsp;</td>
      </tr>
      <?php
}
if ($_smarty_tpl->tpl_vars['provider']->do_else) {
?>
      <tr>
          <td><?php echo smarty_function_xlt(array('t'=>'No Providers Found'),$_smarty_tpl);?>
</td>
      </tr>
      <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
      </tbody>
  </table>
</div>
<?php }
}
