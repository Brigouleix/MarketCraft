import { useQuery } from '@tanstack/react-query';
import { categoriesAPI } from '../services/api';

/**
 * Liste des catégories existantes (table `categories`).
 * Source unique pour le filtre du catalogue et le formulaire produit,
 * afin que tout produit créé soit retrouvable par les filtres.
 */
export function useCategories(options = {}) {
  return useQuery({
    queryKey: ['categories'],
    queryFn: async () => {
      const { data } = await categoriesAPI.getAll();
      return data.data ?? data;
    },
    staleTime: 1000 * 60 * 30,
    ...options,
  });
}
