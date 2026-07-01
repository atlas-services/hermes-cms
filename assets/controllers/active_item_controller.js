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

    /**
     * Largeur grille (1–12) pour la modale (template2), sections type liste uniquement.
     */
    async persistTemplate2Width(event) {
        const select = event.currentTarget;
        const item = select.closest('.list-items');
        const root = select.closest('tbody');

        const type = root?.dataset?.type;
        const locale = root?.dataset?.locale;
        const id = item?.dataset?.itemId;

        if (!type || !locale || !id) {
            console.error('persistTemplate2Width: contexte tbody / ligne manquant');
            return;
        }

        const template2_width = parseInt(select.value, 10);

        if (Number.isNaN(template2_width) || template2_width < 1 || template2_width > 12) {
            console.error('persistTemplate2Width: valeur invalide');
            return;
        }

        const url = `/${locale}/admin/update-template2-width`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id, type, template2_width }),
            });

            if (!response.ok) {
                console.error('Erreur mise à jour template2_width', await response.text());
            }
        } catch (e) {
            console.error('Network error:', e);
        }
    }

    async _postSectionField(url, event, bodyExtra) {
        const item = event.currentTarget.closest('.list-items');
        const root = event.currentTarget.closest('tbody');
        const type = root?.dataset?.type;
        const locale = root?.dataset?.locale;
        const id = item?.dataset?.itemId;
        if (!type || !locale || !id) {
            return false;
        }
        try {
            const response = await fetch(`/${locale}/admin/${url}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, type, ...bodyExtra }),
            });
            if (!response.ok) {
                console.error(`Erreur ${url}`, await response.text());
                return false;
            }
            return true;
        } catch (e) {
            console.error('Network error:', e);
            return false;
        }
    }

    async persistSectionTemplateNbCol(event) {
        const v = parseInt(event.currentTarget.value, 10);
        if (Number.isNaN(v) || v < 1 || v > 12) {
            return;
        }
        await this._postSectionField('update-section-template-nb-col', event, { template_nb_col: v });
    }

    async persistSectionTransparent(event) {
        await this._postSectionField('update-section-transparent', event, {
            transparent: event.currentTarget.checked,
        });
        const row = event.currentTarget.closest('.list-items');
        const colorInput = row?.querySelector('.js-section-bgcolor');
        if (colorInput) {
            colorInput.disabled = event.currentTarget.checked;
        }
    }

    async persistSectionTemplateBgcolor(event) {
        const row = event.currentTarget.closest('.list-items');
        const ok = await this._postSectionField('update-section-template-bgcolor', event, {
            template_bgcolor: event.currentTarget.value,
        });
        if (!ok || !row) {
            return;
        }
        const transparentInput = row.querySelector('[data-action*="persistSectionTransparent"]');
        if (transparentInput) {
            transparentInput.checked = false;
        }
        event.currentTarget.disabled = false;
    }

    async persistSectionTemplateColor(event) {
        await this._postSectionField('update-section-template-color', event, {
            template_color: event.currentTarget.value,
        });
    }

    async persistSectionTemplateImageFilter(event) {
        await this._postSectionField('update-section-template-image-filter', event, {
            template_image_filter: event.currentTarget.value,
        });
    }

    async persistSectionListeTemplate(event) {
        const select = event.currentTarget;
        const v = parseInt(select.value, 10);
        if (Number.isNaN(v) || v < 1) {
            console.error('persistSectionListeTemplate: template_id invalide');
            return;
        }
        const ok = await this._postSectionField('update-section-liste-template', event, { template_id: v });
        if (!ok) {
            const prev = select.dataset.previousValue;
            if (prev !== undefined && prev !== '') {
                select.value = prev;
            }
            return;
        }
        select.dataset.previousValue = String(v);
        const row = select.closest('.list-items');
        const gsapWrap = row?.querySelector('.js-section-gsap-effect-wrap');
        const selected = select.options[select.selectedIndex];
        const code = selected?.dataset?.templateCode ?? '';
        if (gsapWrap) {
            gsapWrap.classList.toggle('d-none', code !== 'folio2');
        }
    }

    async persistSectionGsapImageEffect(event) {
        await this._postSectionField('update-section-gsap-image-effect', event, {
            template_gsap_image_effect: event.currentTarget.value,
        });
    }

    async persistSectionLocale(event) {
        const locale = String(event.currentTarget.value || '').trim().toLowerCase();
        if (!locale) {
            return;
        }
        await this._postSectionField('update-section-locale', event, { locale });
    }
}
