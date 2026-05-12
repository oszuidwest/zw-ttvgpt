/**
 * ZW TTVGPT Settings page JavaScript (ES Module).
 *
 * Toggles the legacy fine-tuned model input when the matching select option
 * is chosen. Configuration arrives via window.zwTTVGPTSettings.
 *
 * @package ZW_TTVGPT
 */

const config = window.zwTTVGPTSettings;
if (!config) {
    console.warn(
        'zw-ttvgpt: settings config missing, legacy model toggle inactive',
    );
} else {
    const select = document.getElementById('zw_ttvgpt_model_select');
    const wrapper = document.getElementById(
        'zw_ttvgpt_legacy_fine_tuned_wrapper',
    );
    const legacyInput = document.getElementById(
        'zw_ttvgpt_legacy_fine_tuned_model',
    );

    if (select && wrapper && legacyInput) {
        const { fieldName, legacyOptionValue } = config;

        select.addEventListener('change', () => {
            const isLegacy = select.value === legacyOptionValue;
            wrapper.style.display = isLegacy ? 'block' : 'none';

            if (isLegacy) {
                select.removeAttribute('name');
                legacyInput.setAttribute('name', fieldName);
                legacyInput.setAttribute('required', 'required');
            } else {
                select.setAttribute('name', fieldName);
                legacyInput.removeAttribute('name');
                legacyInput.removeAttribute('required');
            }
        });
    }
}
