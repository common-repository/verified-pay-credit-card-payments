import {AbstractModule} from "./AbstractModule";
import {VerifiedPay} from "./VerifiedPay";
import {PaymentResponse} from "./structs/PaymentResponse";
import {Gateway} from "./Gateway";
import {PaymentMap} from "./structs/PaymentMap";
import {PaymentType, RestPaymentParams} from "./structs/RestPaymentParams";
import {PaymentButtonID} from "./structs/PaymentButtonID";

interface FrameSizeMsg {
    height: number;
}

export class Payment extends AbstractModule {
    protected gateway: Gateway;
    protected payments = new PaymentMap(); // pending payments to keep track
    protected paymentType: PaymentType = "WP";
    protected currentPayID: string = ""; // postID-counter

    constructor(plugin: VerifiedPay) {
        super(plugin);
        this.gateway = new Gateway(this.plugin.getConfig());
        const config = this.plugin.getConfig();

        this.addPayFrameListeners();
        this.plugin.$(this.plugin.window.document).ready(($) => {
            if (config.woocommerce !== undefined && config.woocommerce.paymentPage === true) {
                //this.isWoocommercePayment = this.plugin.$("body").hasClass("woocommerce-order-received") === true;
                this.paymentType = "WC";
            }
            else if (this.plugin.$("h3.rcp_header").length !== 0)
                this.paymentType = "RCP";
        });
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################

    protected addPayFrameListeners() {
        const config = this.plugin.getConfig();
        const onMessage = (event) => {
            if (event.origin !== config.gatewayOrigin) { // TODO use event.target === "vpay-plugin" ?
            //if (event.origin.indexOf(config.gatewayOrigin) === -1) {
                this.webHelpers.log(["Skipped message from different origin:", event.origin, config.gatewayOrigin]);
                return;
            }

            // TODO currently our security relies on origin property from browser event.
            //  we should add another check such as SHA2 signature for larger amounts
            let data = event.data;
            //if (typeof(window[data.func]) == "function") {
                //window[data.func].call(null, data.message);
            if (typeof data.func === "string" && typeof(this[data.func]) === "function") {
                try {
                    this[data.func].call(this, data.data);
                }
                catch (err) {
                    console.error("Error calling trusted event callback function: ", err);
                }
            }
        }

        if (window.addEventListener) {
            window.addEventListener("message", onMessage, false);
        }
        // @ts-ignore
        else if (window.attachEvent) {
            // @ts-ignore
            window.attachEvent("onmessage", onMessage, false);
        }
    }

    protected onVpayPaid(data: PaymentResponse): Promise<void> {
        return new Promise<void>((resolve, reject) => {
            if (!data || data.status !== "PAID") {
                this.webHelpers.log(["received payment with invalid state", data.status]);
                return;
            }

            const config = this.plugin.getConfig();
            if (config.reloadAfterPay === true) {
                this.plugin.window.location.reload();
                return;
            }
            /*
            this.scheduleRemovePayFrame();

            const verifiedTxId = data.tx_id;
            let payment = this.payments.mergeWithExisting(verifiedTxId, data);
            const buttonWrapper = this.plugin.$(".ct-btn-wrap-" + this.currentPayID);
            const button = buttonWrapper.find(".ct-button-tip");

            const url = "wp-json/verifiedpay/v1/register-payment";
            let params = this.paymentType === "WP" ? RestPaymentParams.fromButton(config, button, data) : RestPaymentParams.fromWoocommerceOrder(config, data);
            this.webHelpers.getApi(url, params, (data) => {
                if (data.data === undefined || data.error === true) {
                    reject({text: "invalid payment response", res: data});
                    return;
                }

                resolve();

                if (this.paymentType === "WC") {
                    // nothing to do currently. we show the frame a few seconds longer with confirmation icon
                }
                else if (this.paymentType === "RCP") {
                    // nothing yet. done via full page payment and their webhook
                }
            });
             */
        })
    }

    protected onVpayReturnToMerchant(data: any) {
        this.plugin.window.location.reload(); // we come from vpay after card can not be charged
    }

    protected onVpayFrameSize(data: FrameSizeMsg) {
        this.plugin.$(".ct-frame-pay iframe").height(data.height).attr("height", data.height);
    }

    protected showPaymentFrame(button: Element) {
        // there can only be 1 payment active
        // hide all other payment frames (and show there buttons)
        this.plugin.$(".ct-button-frame").fadeOut("slow");
        this.plugin.$(".ct-button-wrap").fadeIn("slow");

        const config = this.plugin.getConfig();
        const $btn = this.plugin.$(button);
        //const postID = parseInt($btn.attr("data-id"));
        const buttonIdStr = $btn.attr("data-id");
        this.currentPayID = buttonIdStr;
        const paymentButtonID = new PaymentButtonID(buttonIdStr);
        const amount = parseFloat($btn.attr("data-amount"));
        const currency = $btn.attr("data-currency");
        this.plugin.$(".ct-btn-wrap-" + buttonIdStr).fadeOut("slow");

        // create an iframe and append it
        const frame = document.createElement("iframe");
        const type = this.paymentType === "WC" ? config.tr.order : config.tr.post; // WC frame is currently added on server, not here
        frame.src = this.gateway.getPayFrameUrl(paymentButtonID.postID, amount, currency, type, "WooCommerce Order");
        frame.scrolling = "no";
        frame.style.overflow = "hidden";
        frame.width = "400px";
        frame.height = "800px";
        const frameHolder = this.plugin.$(".ct-button-frame-" + buttonIdStr);
        frameHolder.append(frame);
        frameHolder.fadeIn("slow");

        const verifiedTxId = this.gateway.getLastGeneratedTxId();
        let paymentEmpty = new PaymentResponse();
        if ($btn.attr("data-restricted") === "1")
            paymentEmpty.is_restricted = true;
        this.payments.set(verifiedTxId, paymentEmpty);
    }

    protected updateEditableButtonAmount(input: Element) {
        const config = this.plugin.getConfig();
        const $input = this.plugin.$(input);
        const amount = parseFloat($input.val());
        const $buttonWrap = this.plugin.$(".ct-btn-wrap-" + this.currentPayID);
        $buttonWrap.find(".ct-button-tip").attr("data-amount", amount);
        $buttonWrap.find(".ct-btn-display-amount").text(amount);
    }

    protected scheduleRemovePayFrame() {
        const config = this.plugin.getConfig();
        const timeoutSec = this.paymentType === "WC" ? 5 : 0;
        const frameID = this.paymentType === "WP" ? 0 : config.woocommerce.orderID; // TODO RCP
        const selector = ".ct-button-frame-" + frameID + ", .ct-button-frame-" + this.currentPayID;
        setTimeout(() => {
            // TODO what if we want multiple buttons per post? storing a new DB entry per shortcode is overkill (as Cashtippr)
            this.plugin.$(selector).fadeOut("slow");
            setTimeout(() => {
                this.plugin.$(selector).remove();
            }, 1000);
        }, timeoutSec * 1000);
    }
}
