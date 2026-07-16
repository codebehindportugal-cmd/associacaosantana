import { usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

/**
 * reCAPTCHA v3 partilhado. Uso:
 *   const { obterToken } = useRecaptcha();
 *   form.recaptcha_token = await obterToken('contacto');
 */
export function useRecaptcha() {
    const siteKey = usePage().props.recaptcha_site_key;

    onMounted(() => {
        if (!siteKey || document.getElementById('recaptcha-script')) return;
        const s = document.createElement('script');
        s.id = 'recaptcha-script';
        s.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
        document.head.appendChild(s);
    });

    const obterToken = (action = 'submit') => new Promise((resolve) => {
        if (!siteKey || !window.grecaptcha) return resolve('');
        window.grecaptcha.ready(() => {
            window.grecaptcha.execute(siteKey, { action })
                .then(resolve)
                .catch(() => resolve(''));
        });
    });

    return { siteKey, obterToken };
}
