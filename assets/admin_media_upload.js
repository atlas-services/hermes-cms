import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import XHRUpload from '@uppy/xhr-upload';
import '@uppy/core/dist/style.min.css';
import '@uppy/dashboard/dist/style.min.css';

function readJsonArray(raw) {
    if (!raw) {
        return null;
    }
    try {
        const v = JSON.parse(raw);
        return Array.isArray(v) ? v : null;
    } catch {
        return null;
    }
}

/**
 * Chemin relatif choisi via « dossier » (webkitdirectory / File System Access).
 * Uppy Dashboard le met dans meta.relativePath ; le File natif peut avoir webkitRelativePath.
 * Ne pas n’en lire qu’un seul : sans meta.relativePath, l’envoi se fait à plat dans le dossier courant.
 */
function folderRelativePath(file) {
    const fromMeta = file.meta?.relativePath;
    if (typeof fromMeta === 'string' && fromMeta.length > 0) {
        return fromMeta.replace(/\\/g, '/');
    }
    const fromFile = file.data?.webkitRelativePath;
    if (typeof fromFile === 'string' && fromFile.length > 0) {
        return fromFile.replace(/\\/g, '/');
    }

    return '';
}

function init() {
    const root = document.getElementById('admin-media-upload');
    if (!root) {
        return;
    }

    const endpoint = root.dataset.uploadUrl;
    const csrf = root.dataset.csrfToken || '';
    const maxFileSize = root.dataset.maxFileSize ? Number(root.dataset.maxFileSize) : null;
    const allowedFileTypes = readJsonArray(root.dataset.allowedFileTypes);

    if (!endpoint) {
        return;
    }

    const restrictions = {
        maxNumberOfFiles: 500,
        // Pas de plafond sur la taille totale de la file : seule maxFileSize (par fichier) s’applique.
        maxTotalFileSize: null,
    };
    if (Number.isFinite(maxFileSize) && maxFileSize > 0) {
        restrictions.maxFileSize = maxFileSize;
    }
    if (allowedFileTypes?.length) {
        restrictions.allowedFileTypes = allowedFileTypes;
    }

    const mount = root.querySelector('[data-uppy-dashboard-mount]');
    if (!mount) {
        return;
    }

    const currentPath = root.dataset.currentPath || '';

    const uppy = new Uppy({
        id: 'admin-media',
        restrictions,
    });

    uppy.on('file-added', (file) => {
        const wr = folderRelativePath(file);
        uppy.setFileMeta(file.id, {
            upload_base_path: currentPath,
            file_relative_path: wr,
        });
    });

    // Au moment où l’upload démarre, réappliquer le chemin (certains chemins ne sont fiables qu’ici).
    uppy.on('upload', (_uploadId, files) => {
        for (const file of files) {
            if (!file || file.isRemote) {
                continue;
            }
            const wr = folderRelativePath(file);
            uppy.setFileMeta(file.id, {
                upload_base_path: currentPath,
                file_relative_path: wr,
            });
        }
    });

    uppy
        .use(Dashboard, {
            inline: true,
            target: mount,
            height: 460,
            showProgressDetails: true,
            // Beaucoup d’images : la génération de miniatures dans le Dashboard monopolise le CPU
            // et ralentit fortement les XHR après ~30 % de progression. Désactivé pour garder l’envoi fluide.
            disableThumbnailGenerator: true,
            proudlyDisplayPoweredByUppy: false,
            // Fichiers et dossiers (webkitdirectory) : Chrome, Edge, Firefox récents — pas Safari.
            fileManagerSelectionType: 'both',
        })
        .use(XHRUpload, {
            endpoint,
            fieldName: 'file',
            formData: true,
            bundle: false,
            limit: 10,
            allowedMetaFields: ['upload_base_path', 'file_relative_path'],
            headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {},
        });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
