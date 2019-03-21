import ValidationService from "services/ValidationService";
const ApiService          = require("services/ApiService");

Vue.component("bankAccount-input", {
    props: {
        template:
        {
            type: String,
            default: "#vue-bankAccount-input"
        }
    },

    data()
    {
        return {
            bankAccountOwner: "bAO",
            bankName: "bN",
            bankIban: "bI",
            bankSwift: "bS"
        };
    },

    created()
    {
        this.$options.template = this.template;
    },

    methods: {
        validateData()
        {
            this.isDisabled = true;

            ValidationService.validate($("#bankAccount-input-form_" + this._uid))
                .done(() =>
                {
                    this.save();
                })
                .fail(invalidFields =>
                {
                    ValidationService.markInvalidFields(invalidFields, "error");

                    this.isDisabled = false;
                });
        },
        save()
        {
            //TODO save bank data
            ApiService.post("/rest/io/checkout/payment");
        }
    }
});
