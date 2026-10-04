/**
 * Vérification immédiate (côté navigateur) d'un fichier déposé pour un type
 * de document : format attendu (TypeDocument::formats_acceptes) et taille
 * maximale — mêmes règles que le serveur (SaveEleveWizardRequest,
 * StoreDocumentEleveRequest, TypeDocument::accepteExtension()), mais signalées
 * dès le dépôt au lieu d'attendre l'enregistrement, où le navigateur aurait
 * déjà vidé tous les champs fichier du formulaire.
 */
export const TAILLE_MAX_DOCUMENT_OCTETS = 5 * 1024 * 1024;

/** Extensions équivalentes à un format déclaré (ex. « JPG » couvre .jpeg). */
const EQUIVALENTS = { JPEG: 'JPG' };

/**
 * @param {string|undefined} formatsAttribute  "PDF,JPG" (data-formats), vide = tout format accepté
 * @returns {string[]}
 */
export function parseFormats(formatsAttribute) {
    return (formatsAttribute || '')
        .split(',')
        .map((format) => format.trim().toUpperCase())
        .filter(Boolean);
}

/**
 * Valeur de l'attribut `accept` d'un <input type="file"> pour ces formats.
 *
 * @param {string[]} formats
 * @returns {string}
 */
export function acceptAttribute(formats) {
    return formats
        .flatMap((format) => (format === 'JPG' ? ['.jpg', '.jpeg'] : [`.${format.toLowerCase()}`]))
        .join(',');
}

/**
 * @param {File} file
 * @param {string[]} formats
 * @param {number} [tailleMaxOctets] limite réelle du serveur si plus basse que 5 Mo (voir App\Support\LimitesEnvoi)
 * @returns {string|null} message d'erreur, ou null si le fichier est valide
 */
export function erreurFichierDocument(file, formats, tailleMaxOctets = TAILLE_MAX_DOCUMENT_OCTETS) {
    const extensionBrute = (file.name.split('.').pop() || '').toUpperCase();
    const extension = EQUIVALENTS[extensionBrute] ?? extensionBrute;

    if (formats.length > 0 && (!file.name.includes('.') || !formats.includes(extension))) {
        return `Format non accepté (${file.name}). Formats attendus : ${formats.join(', ')}.`;
    }

    if (file.size > tailleMaxOctets) {
        return `Fichier trop volumineux (${enMo(file.size)}). Taille maximale : ${enMo(tailleMaxOctets)}.`;
    }

    return null;
}

/**
 * @param {number} octets
 * @returns {string} ex. « 5,3 Mo »
 */
export function enMo(octets) {
    const mo = octets / (1024 * 1024);
    return `${Number.isInteger(mo) ? mo : mo.toFixed(1).replace('.', ',')} Mo`;
}
