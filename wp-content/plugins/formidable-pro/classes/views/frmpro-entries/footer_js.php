<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<script>
/*<![CDATA[*/
<?php
if ( isset( $frm_vars['tinymce_loaded'] ) && $frm_vars['tinymce_loaded'] === true ) {
	echo 'var ajaxurl="' . esc_url( admin_url( 'admin-ajax.php', 'relative' ) ) . '";' . "\n";
}

if ( ! empty( $frm_vars['rules'] ) ) {
?>var frmrules=<?php echo json_encode( $frm_vars['rules'] ); ?>;
if(typeof __FRMRULES === 'undefined'){__FRMRULES=frmrules;}
else{__FRMRULES=jQuery.extend({},__FRMRULES,frmrules);}<?php
}

if ( ! empty( $frm_vars['lookup_fields'] ) ) {
?>var frmlookup=<?php echo json_encode( $frm_vars['lookup_fields'] ); ?>;
if(typeof __FRMLOOKUP === 'undefined'){__FRMLOOKUP=frmlookup;}
else{__FRMLOOKUP=jQuery.extend({},__FRMLOOKUP,frmlookup);}<?php
}

if ( ! empty( $frm_vars['google_graphs'] ) ) {
	echo '__FRMTABLES=' . json_encode( $frm_vars['google_graphs'] ) . ";\n";
	echo 'frmProForm.loadGoogle();' . "\n";
}

FrmProFormsHelper::load_chosen_js( $frm_vars );
FrmProFormsHelper::load_hide_conditional_fields_js( $frm_vars );
FrmProFormsHelper::load_calc_js( $frm_vars );
FrmProFormsHelper::load_rte_js( $frm_vars );
FrmProFormsHelper::load_datepicker_js( $frm_vars );
FrmProFormsHelper::load_currency_js( $frm_vars );
FrmProFormsHelper::load_input_mask_js();
FrmProLookupFieldsController::load_check_dependent_lookup_js( $frm_vars );
FrmProFormsHelper::load_dropzone_js( $frm_vars );

?>
/*]]>*/
</script>
