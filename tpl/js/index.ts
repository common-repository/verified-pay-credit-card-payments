import {VerifiedPay} from "./src/VerifiedPay";

let verifiedPayPlugin = new VerifiedPay(window as any, jQuery);
(window as any).verifiedPayPlugin = verifiedPayPlugin;
