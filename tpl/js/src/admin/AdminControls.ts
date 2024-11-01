import {AbstractModule} from "../AbstractModule";
import {VerifiedPay} from "../VerifiedPay";

export class AdminControls extends AbstractModule {
    constructor(plugin: VerifiedPay) {
        super(plugin);
    }

    public init() {
        if (this.plugin.$("body").attr("class").indexOf("verifiedpay") === -1)
            return; // not our plugin settings page

        this.plugin.getTooltips().initToolTips();
        this.plugin.$(this.plugin.window.document).ready(($) => {

        });
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################

}
