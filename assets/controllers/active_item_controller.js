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
}
