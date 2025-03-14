jQuery(document).on("gform_load_field_settings", function(event, field, form) {
    jQuery("#field_awesome_addto").val(field["awesomeAddTo"]);
    jQuery("#field_awesome_meta_key").val(field["awesomeMetaKey"]);
    var allowedTypes = ['checkbox', 'radio', 'select', 'multiselect', 'phone', 'address', 'name'];
    // Hide "Convert to" for name fields.
    if (allowedTypes.indexOf(field.type) !== -1 && field.type != 'name') {
        jQuery("#field_awesome_convert").closest("li.awesome_convert_setting").show();
        jQuery("#field_awesome_convert").val(field["awesomeConvert"]);
    } else {
        jQuery("#field_awesome_convert").closest("li.awesome_convert_setting").hide();
    }
});
