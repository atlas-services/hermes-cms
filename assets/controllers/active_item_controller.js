import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    switch(event) {
        const item = event.currentTarget.closest('.list-items');
        this.switchActive(item);
    }

    async switchActive(item) {
        const root = this.element.closest('tbody');

        const type = root.dataset.type;
        const locale = root.dataset.locale;
        const id = item.dataset.itemId;

        const url = `/${locale}/admin/switch-active`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id, type }),
            });

            if (!response.ok) {
                console.error('Erreur switch active');
            }

        } catch (e) {
            console.error('Network error:', e);
        }
    }

    /**
     * Mise à jour de template_width (sections), même schéma que switchActive : POST JSON vers l’admin.
     */
    async persistTemplateWidth(event) {
        const select = event.currentTarget;
        const item = select.closest('.list-items');
        const root = select.closest('tbody');

        const type = root?.dataset?.type;
        const locale = root?.dataset?.locale;
        const id = item?.dataset?.itemId;

        if (!type || !locale || !id) {
            console.error('persistTemplateWidth: contexte tbody / ligne manquant');
            return;
        }

        const template_width = parseInt(select.value, 10);

        if (Number.isNaN(template_width) || template_width < 1 || template_width > 12) {
            console.error('persistTemplateWidth: valeur invalide');
            return;
        }

        const url = `/${locale}/admin/update-template-width`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id, type, template_width }),
            });

            if (!response.ok) {
                console.error('Erreur mise à jour template_width', await response.text());
            }
        } catch (e) {
            console.error('Network error:', e);
        }
    }

    /**
     * Modale secondaire (section) : uniquement pour sections type liste ; POST JSON.
     */
    async persistSectionTemplate2(event) {
        const select = event.currentTarget;
        const item = select.closest('.list-items');
        const root = select.closest('tbody');

        const type = root?.dataset?.type;
        const locale = root?.dataset?.locale;
        const id = item?.dataset?.itemId;

        if (!type || !locale || !id) {
            console.error('persistSectionTemplate2: contexte tbody / ligne manquant');
            return;
        }

        const template2_code = select.value === '' ? '' : String(select.value);

        const url = `/${locale}/admin/update-section-template2`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id, type, template2_code }),
            });

            if (!response.ok) {
                console.error('Erreur mise à jour modale section', await response.text());
            }
        } catch (e) {
            console.error('Network error:', e);
        }
    }
}
