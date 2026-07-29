/**
 * Retourne toujours un tableau d'URLs d'images exploitables, que l'API renvoie
 * un tableau déjà décodé ou une chaîne JSON brute (colonne MySQL JSON).
 *
 * Les entrées qui ne sont pas des URLs utilisables sont écartées : les données
 * de démonstration stockent de simples noms de fichiers (« bol-noyer-1.jpg »)
 * qui, utilisés tels quels dans un <img>, sont résolus relativement à la page
 * courante et renvoient un 404. Mieux vaut ne rien afficher et laisser le
 * placeholder prendre le relais.
 */
export function parseImages(images) {
  let liste = [];

  if (Array.isArray(images)) {
    liste = images;
  } else if (typeof images === 'string' && images.trim()) {
    try {
      const parsed = JSON.parse(images);
      liste = Array.isArray(parsed) ? parsed : [];
    } catch {
      liste = [];
    }
  }

  return liste.filter(estUrlUtilisable);
}

/**
 * URL absolue (http/https), chemin absolu (/uploads/…) ou data-URI.
 */
export function estUrlUtilisable(valeur) {
  if (typeof valeur !== 'string') return false;

  const v = valeur.trim();

  return v.startsWith('http://')
    || v.startsWith('https://')
    || v.startsWith('/')
    || v.startsWith('data:image/');
}
