
export class PaymentButtonID {
    public readonly postID: number = 0;
    public readonly buttonNr: number = 0;

    constructor(combinedId: string) {
        const idParts = combinedId.split("-");
        if (idParts.length !== 2)
            throw new Error("PaymentButtonID must have exactly 2 parts: postID-buttonNr");
        this.postID = parseInt(idParts[0]);
        this.buttonNr = parseInt(idParts[1]);
    }

    public getButtonIdStr(): string {
        return `${this.postID}-${this.buttonNr}`;
    }
}
