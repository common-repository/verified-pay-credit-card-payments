import {PaymentResponse} from "./PaymentResponse";

export class PaymentMap extends Map<string, PaymentResponse> { // (verified TX ID, pending payment)
    constructor() {
        super();
    }

    /**
     * Merge certain pre-defined properties from the existing payment onto newData and return it.
     * @param verifiedTxId
     * @param newData
     */
    public mergeWithExisting(verifiedTxId: string, newData: PaymentResponse): PaymentResponse {
        let payment = this.get(verifiedTxId);
        if (payment !== undefined) { // should always be defined in our current payment flow
            newData.is_restricted = payment.is_restricted;
        }
        this.set(verifiedTxId, newData);
        return newData;
    }
}
