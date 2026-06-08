import gsap from 'gsap';

if (typeof window !== 'undefined') {
    window.gsap = gsap;
    window.dispatchEvent(new CustomEvent('hermes:gsap-ready', { detail: { gsap } }));
}

/** Attendre GSAP depuis un script inline dans un post (module app.js peut charger après). */
export function whenGsapReady(callback) {
    if (typeof window !== 'undefined' && window.gsap) {
        callback(window.gsap);
        return;
    }

    const onReady = (event) => {
        window.removeEventListener('hermes:gsap-ready', onReady);
        callback(event.detail?.gsap ?? window.gsap);
    };
    window.addEventListener('hermes:gsap-ready', onReady);
}

export { gsap };
export default gsap;
