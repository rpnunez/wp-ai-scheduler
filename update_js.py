import re

with open('ai-post-scheduler/assets/js/admin.js', 'r') as f:
    content = f.read()

# Add event listener
event_listener = "            $(document).on('click', '.aips-wizard-next', this.wizardNext);\n            $(document).on('click', '.aips-wizard-back', this.wizardBack);\n            $(document).on('click', '.aips-wizard-step', this.wizardStepClick);"
content = content.replace(
    "            $(document).on('click', '.aips-wizard-next', this.wizardNext);\n            $(document).on('click', '.aips-wizard-back', this.wizardBack);",
    event_listener
)

# Add wizardStepClick method
wizard_step_click = """
        /**
         * Go back to the previous wizard step.
         *
         * Does nothing when already on step 1.
         *
         * @param {Event} e - Click event from an `.aips-wizard-back` element.
         */
        wizardBack: function(e) {
            e.preventDefault();
            var currentStep = AIPS.currentWizardStep || 1;

            if (currentStep > 1) {
                AIPS.wizardGoToStep(currentStep - 1);
            }
        },

        /**
         * Handle clicking directly on a progress indicator step.
         *
         * Allows navigating directly to previous steps, or advancing to future
         * steps provided all intermediate steps pass validation.
         *
         * @param {Event} e - Click event from an `.aips-wizard-step` element.
         */
        wizardStepClick: function(e) {
            e.preventDefault();
            var currentStep = AIPS.currentWizardStep || 1;
            var targetStep = parseInt($(this).data('step'));

            if (!targetStep || targetStep === currentStep) {
                return;
            }

            // If going backwards, just go there directly
            if (targetStep < currentStep) {
                AIPS.wizardGoToStep(targetStep);
                return;
            }

            // If going forwards, validate all intermediate steps
            for (var i = currentStep; i < targetStep; i++) {
                if (!AIPS.validateWizardStep(i)) {
                    // Validation failed on step 'i', so we can't proceed past it.
                    // If we are not already on the step that failed, go to it.
                    if (currentStep !== i) {
                        AIPS.wizardGoToStep(i);
                    }
                    return;
                }
            }

            // If all validation passed, go to the target step
            AIPS.wizardGoToStep(targetStep);
        },"""

content = content.replace("""
        /**
         * Go back to the previous wizard step.
         *
         * Does nothing when already on step 1.
         *
         * @param {Event} e - Click event from an `.aips-wizard-back` element.
         */
        wizardBack: function(e) {
            e.preventDefault();
            var currentStep = AIPS.currentWizardStep || 1;

            if (currentStep > 1) {
                AIPS.wizardGoToStep(currentStep - 1);
            }
        },""", wizard_step_click)

with open('ai-post-scheduler/assets/js/admin.js', 'w') as f:
    f.write(content)
