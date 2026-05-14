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

/** Extensions → MIME (aligné serveur quand le navigateur envoie un type vide, fréquent avec webkitdirectory). */
const EXT_TO_MIME = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    jpe: 'image/jpeg',
    png: 'image/png',
    gif: 'image/gif',
    webp: 'image/webp',
    svg: 'image/svg+xml',
    pdf: 'application/pdf',
    mp4: 'video/mp4',
};

function normalizeMime(raw) {
    if (typeof raw !== 'string' || raw === '') {
        return '';
    }
    return raw.split(';')[0].trim().toLowerCase();
}

/**
 * MIME « effectif » pour la validation (évite application/octet-stream sans extension connue).
 */
function effectiveMimeForPolicy(file) {
    let mime = normalizeMime(file.type);
    if (mime && mime !== 'application/octet-stream') {
        return mime;
    }
    const ext = typeof file.extension === 'string' ? file.extension.toLowerCase() : '';
    if (ext && Object.prototype.hasOwnProperty.call(EXT_TO_MIME, ext)) {
        return EXT_TO_MIME[ext];
    }

    return mime;
}

function isFileAllowedByMimeList(file, allowedMimeSet) {
    const mime = effectiveMimeForPolicy(file);
    return mime !== '' && allowedMimeSet.has(mime);
}

const THUMB_EXT = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp']);

function fileExtLower(name) {
    if (typeof name !== 'string' || !name.includes('.')) {
        return '';
    }
    return name.split('.').pop().toLowerCase();
}

function hasListingThumbByName(name) {
    return THUMB_EXT.has(fileExtLower(name));
}

function formatSizeFr(bytes) {
    if (bytes == null || !Number.isFinite(bytes)) {
        return '—';
    }
    if (bytes < 1024) {
        return `${Math.round(bytes)} o`;
    }
    return `${(bytes / 1024).toFixed(1)} Ko`;
}

function buildPublicHref(basePath, path) {
    if (!path) {
        return '';
    }
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    const bp = basePath || '';
    if (path.startsWith('/')) {
        return `${bp}${path}`;
    }

    return `${bp}/${path}`;
}

/** Le fichier est-il listé dans le dossier courant (enfant direct), ou dans un sous-dossier ? */
function isDirectChildOfBrowsePath(relativePath, browsePath) {
    if (typeof relativePath !== 'string' || relativePath === '') {
        return false;
    }
    const browse = (browsePath || '').replace(/^\/+|\/+$/g, '');
    const i = relativePath.lastIndexOf('/');
    const parent = i === -1 ? '' : relativePath.slice(0, i);

    return parent === browse;
}

/**
 * Ajoute une ligne fichier au tableau (réponse JSON upload) sans recharger la page.
 */
function appendMediaTableRow(root, { filename, url, sizeBytes, relativePath }) {
    const tbody = document.getElementById('admin-media-files-tbody');
    if (!tbody || !filename || !url) {
        return;
    }

    document.getElementById('admin-media-empty-row')?.remove();

    const basePath = root.dataset.requestBasePath || '';
    const href = buildPublicHref(basePath, url);

    const openLabel = root.dataset.labelOpenFile || 'Open';
    const typeLabel = root.dataset.labelTypeFile || 'File';
    const deleteUrl = root.dataset.deleteFileUrl || '';
    const csrf = root.dataset.csrfToken || '';
    const browsePath = root.dataset.currentPath || '';
    const deleteLabel = root.dataset.labelDeleteFile || 'Delete';
    const confirmTpl = root.dataset.confirmDeleteFile || '';

    const tr = document.createElement('tr');

    const tdPrev = document.createElement('td');
    tdPrev.className = 'text-center align-middle p-1';
    if (hasListingThumbByName(filename)) {
        const wrap = document.createElement('a');
        wrap.href = href;
        wrap.target = '_blank';
        wrap.rel = 'noopener';
        wrap.className = 'd-inline-block border rounded overflow-hidden bg-light';
        wrap.style.width = '3rem';
        wrap.style.height = '3rem';
        const img = document.createElement('img');
        img.src = href;
        img.alt = '';
        img.width = 48;
        img.height = 48;
        img.loading = 'lazy';
        img.decoding = 'async';
        img.className = 'w-100 h-100';
        img.style.objectFit = 'cover';
        wrap.appendChild(img);
        tdPrev.appendChild(wrap);
    } else {
        const span = document.createElement('span');
        span.className = 'text-secondary';
        const i = document.createElement('i');
        i.className = 'fa-regular fa-file fa-lg';
        span.appendChild(i);
        tdPrev.appendChild(span);
    }
    tr.appendChild(tdPrev);

    const tdName = document.createElement('td');
    tdName.className = 'align-middle';
    tdName.textContent = filename;
    tr.appendChild(tdName);

    const tdType = document.createElement('td');
    tdType.className = 'align-middle';
    tdType.textContent = typeLabel;
    tr.appendChild(tdType);

    const tdSize = document.createElement('td');
    tdSize.className = 'align-middle';
    tdSize.textContent = formatSizeFr(sizeBytes);
    tr.appendChild(tdSize);

    const tdLink = document.createElement('td');
    tdLink.className = 'align-middle';
    const a = document.createElement('a');
    a.href = href;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = openLabel;
    tdLink.appendChild(a);
    tr.appendChild(tdLink);

    const tdAct = document.createElement('td');
    tdAct.className = 'align-middle';
    if (deleteUrl && csrf && typeof relativePath === 'string' && relativePath !== '') {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = deleteUrl;
        form.className = 'd-inline';
        const msg = confirmTpl.includes('__NAME__')
            ? confirmTpl.split('__NAME__').join(filename)
            : `${confirmTpl} ${filename}`;
        form.dataset.confirm = msg;
        form.addEventListener('submit', (ev) => {
            if (!window.confirm(form.dataset.confirm)) {
                ev.preventDefault();
            }
        });
        const t1 = document.createElement('input');
        t1.type = 'hidden';
        t1.name = '_token';
        t1.value = csrf;
        form.appendChild(t1);
        const t2 = document.createElement('input');
        t2.type = 'hidden';
        t2.name = 'path';
        t2.value = relativePath;
        form.appendChild(t2);
        const t3 = document.createElement('input');
        t3.type = 'hidden';
        t3.name = 'browse_path';
        t3.value = browsePath;
        form.appendChild(t3);
        const btn = document.createElement('button');
        btn.type = 'submit';
        btn.className = 'btn btn-sm btn-outline-danger text-nowrap';
        btn.textContent = deleteLabel;
        form.appendChild(btn);
        tdAct.appendChild(form);
    } else {
        tdAct.textContent = '—';
    }
    tr.appendChild(tdAct);

    tbody.appendChild(tr);
}

function init() {
    const root = document.getElementById('admin-media-upload');
    if (!root) {
        return;
    }

    const endpoint = root.dataset.uploadUrl;
    const csrf = root.dataset.csrfToken || '';
    const maxFileSize = root.dataset.maxFileSize ? Number(root.dataset.maxFileSize) : null;
    const allowedMimeList = readJsonArray(root.dataset.allowedFileTypes);
    const rejectMessageTemplate = root.dataset.rejectFileMsg || 'File not allowed: __NAME__';

    if (!endpoint) {
        return;
    }

    const restrictions = {
        maxNumberOfFiles: 500,
        // Pas de plafond sur la taille totale de la file : seule maxFileSize (par fichier) s’applique.
        maxTotalFileSize: null,
        // Ne pas mettre allowedFileTypes ici : le Dashboard les passe à <input accept="…"> y compris pour
        // webkitdirectory. Avec des MIME précis, Chrome filtre souvent tout le dossier (types vides en parcours).
        // La liste autorisée est appliquée dans onBeforeFileAdded (comme le contrôleur PHP).
    };
    if (Number.isFinite(maxFileSize) && maxFileSize > 0) {
        restrictions.maxFileSize = maxFileSize;
    }

    const allowedMimeSet = new Set(
        (allowedMimeList || []).map((m) => (typeof m === 'string' ? normalizeMime(m) : '')).filter(Boolean),
    );

    const mount = root.querySelector('[data-uppy-dashboard-mount]');
    if (!mount) {
        return;
    }

    const currentPath = root.dataset.currentPath || '';

    const addMoreHint = root.dataset.addMoreHint || '';

    const uppy = new Uppy({
        id: 'admin-media',
        restrictions,
        onBeforeFileAdded: (file, files) => {
            if (Object.prototype.hasOwnProperty.call(files, file.id)) {
                return false;
            }
            if (allowedMimeSet.size > 0 && !isFileAllowedByMimeList(file, allowedMimeSet)) {
                const text = rejectMessageTemplate.includes('__NAME__')
                    ? rejectMessageTemplate.split('__NAME__').join(file.name || '')
                    : `${rejectMessageTemplate} ${file.name || ''}`;
                uppy.info({ message: text }, 'error', 8000);
                return false;
            }

            return true;
        },
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
            height: 360,
            showProgressDetails: true,
            // Vignettes légères avant envoi (largeur réduite vs défaut 280) pour limiter la charge CPU sur gros lots.
            disableThumbnailGenerator: false,
            thumbnailWidth: 88,
            thumbnailHeight: 88,
            waitForThumbnailsBeforeUpload: false,
            proudlyDisplayPoweredByUppy: false,
            note: addMoreHint || undefined,
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

    uppy.on('upload-success', (file, response) => {
        const body = response?.body;
        if (!body || body.success !== true || typeof body.url !== 'string') {
            return;
        }
        const relPath = typeof body.relativePath === 'string' ? body.relativePath : '';
        if (!isDirectChildOfBrowsePath(relPath, currentPath)) {
            return;
        }
        appendMediaTableRow(root, {
            filename: typeof body.filename === 'string' ? body.filename : file?.name,
            url: body.url,
            sizeBytes: file?.size,
            relativePath: relPath,
        });
    });

    // Après un envoi : dossier (webkitdirectory) → rechargement pour voir toute l’arborescence créée ;
    // fichiers seuls → on vide la file Uppy pour retrouver browse files / browse folders.
    uppy.on('complete', (result) => {
        const successful = result?.successful ?? [];
        const failed = result?.failed ?? [];
        const touched = [...successful, ...failed];
        const usedFolderPick = touched.some((file) => {
            if (!file) {
                return false;
            }
            const wr = folderRelativePath(file);
            return typeof wr === 'string' && wr.length > 0;
        });
        if (usedFolderPick) {
            window.location.reload();
            return;
        }
        uppy.clear();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
