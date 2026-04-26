import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item'];

    moveUp(event) {
        const item = event.currentTarget.closest('.list-items');
        const previousItem = item.previousElementSibling;

        if (previousItem) {
            item.parentNode.insertBefore(item, previousItem);
             this.updatePositions();
        }
    }

    moveDown(event) {
        const item = event.currentTarget.closest('.list-items');
        const nextItem = item.nextElementSibling;

        if (nextItem) {
            item.parentNode.insertBefore(nextItem, item);
            this.updatePositions();
        }
    }

    async updatePositions() {
        const positions = [];
        const type = this.element.dataset.type;
        const locale = this.element.dataset.locale;
        this.itemTargets.forEach((item, index) => {
            const id = item.dataset.itemId; // Assurez-vous que chaque item a un data-item-id
            positions.push({ id: id, position: index + 1 });
        });

        await fetch('/' + locale + '/admin' + '/update-positions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ positions: positions, type: type }),
        });
    }

}
