import {VerifiedPay} from "./VerifiedPay";
import {WebHelpers} from "./WebHelpers";


export class AbstractModule {
    protected plugin: VerifiedPay;
    protected webHelpers: WebHelpers;

    constructor(plugin: VerifiedPay, webHelpers: WebHelpers = null) {
        this.plugin = plugin;
        this.webHelpers = webHelpers ? webHelpers : this.plugin.getWebHelpers();
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################
}
