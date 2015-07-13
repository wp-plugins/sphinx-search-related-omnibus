jQuery(document).ready(function () {
    jQuery("#sphinxOmnibusConflocation-selector").change(function () {
        if (jQuery(this).val() == "none") {
            jQuery("#sphinxOmnibusConflocation").attr("value", null);
            jQuery("#sphinxOmnibusConflocation").attr("type", "hidden");

        }
        if (jQuery(this).val() == "custom") {
            jQuery("#sphinxOmnibusConflocation").attr("value", null);
            jQuery("#sphinxOmnibusConflocation").attr("type", "text");

        }
        if (jQuery(this).val() != "custom" && jQuery(this).val() != "none") {
            jQuery("#sphinxOmnibusConflocation").attr("value", jQuery(this).val());
            jQuery("#sphinxOmnibusConflocation").attr("type", "hidden");
        }
        //sphinxOmnibus-conflocation
    });
});