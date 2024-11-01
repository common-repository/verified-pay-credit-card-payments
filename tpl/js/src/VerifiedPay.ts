import {Tooltips} from "./admin/Tooltips";
import {WebHelpers, WebHelpersConfig} from "./WebHelpers";
import {Payment} from "./Payment";
import {AdminControls} from "./admin/AdminControls";
import {BrowserWindow} from "./types";

export interface VerifiedPayConfig extends WebHelpersConfig {
    cookieLifeDays: number;
    cookiePath: string;
    siteUrl: string;
    gatewayOrigin: string;
    frameUrl: string;
    reloadAfterPay: boolean;

    woocommerce?: {
        amount: number;
        currency: string;
        orderID: number;
        paymentPage: boolean;
    }

    // localizations
    tr: {
        order: string;
        post: string;
    }
}

export interface VerifiedPayApiRes {
    error: boolean;
    errorMsg: string;
    data: any[];
}

export class VerifiedPay {
    protected static readonly CONSENT_COOKIE_NAME = "vp-ck";
    protected static readonly CONFIRM_COOKIES_MSG = "#ct-cookieMsg";
    protected static readonly CONFIRM_COOKIES_BTN = "#ct-confirmCookies";
    // TODO separate entryPoints + classes for admin + public code? but tooltips and other admin stuff can be used publicly too (and is quite small)

    public readonly window: BrowserWindow;
    public readonly $: JQueryStatic;

    protected config: VerifiedPayConfig;
    protected webHelpers: WebHelpers;
    protected adminControls: AdminControls;
    protected tooltips: Tooltips;
    protected payment: Payment;

    constructor(window: BrowserWindow, $: JQueryStatic) {
        this.window = window;
        this.$ = $;
        this.config = this.window['verifiedPayCfg'] || {};
        this.config.consentCookieName = VerifiedPay.CONSENT_COOKIE_NAME;
        this.config.confirmCookiesMsg = VerifiedPay.CONFIRM_COOKIES_MSG;
        this.config.confirmCookiesBtn = VerifiedPay.CONFIRM_COOKIES_BTN;

        this.webHelpers = new WebHelpers(this.window, this.$, this.config);
        this.tooltips = new Tooltips(this, this.webHelpers);
        this.adminControls = new AdminControls(this);
        this.payment = new Payment(this);
        this.$(this.window.document).ready(($) => {
            this.adminControls.init();
            this.webHelpers.checkCookieConsent();
        });
    }

    public getConfig() {
        return this.config;
    }

    public getTooltips() {
        return this.tooltips;
    }

    public getWebHelpers() {
        return this.webHelpers;
    }

    // ################################################################
    // ###################### PRIVATE FUNCTIONS #######################
}
