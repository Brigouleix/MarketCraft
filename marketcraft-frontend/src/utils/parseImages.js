/**
 * Retourne toujours un tableau d'URLs d'images, que l'API renvoie
 * un tableau déjà décodé ou une chaîne JSON brute (colonne MySQL JSON).
 */
export function parseImages(images) {
  if (Array.isArray(images)) return images;
  if (typeof images === 'string' && images.trim()) {
    try {
      const parsed = JSON.parse(images);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
}
