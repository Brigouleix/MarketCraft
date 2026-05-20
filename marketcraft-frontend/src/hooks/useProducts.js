import { useQuery } from '@tanstack/react-query';
import { productsAPI } from '../services/api';

/**
 * Fetches a paginated, filtered list of products.
 *
 * @param {Object} filters  - { search, categorie, prix_min, prix_max, note_min, tri, page, per_page }
 * @param {Object} options  - additional react-query options
 */
export function useProducts(filters = {}, options = {}) {
  const params = {
    page: filters.page || 1,
    per_page: filters.per_page || 12,
    ...(filters.search && { search: filters.search }),
    ...(filters.categorie && { categorie: filters.categorie }),
    ...(filters.prix_min !== undefined && filters.prix_min !== '' && { prix_min: filters.prix_min }),
    ...(filters.prix_max !== undefined && filters.prix_max !== '' && { prix_max: filters.prix_max }),
    ...(filters.note_min !== undefined && filters.note_min > 0 && { note_min: filters.note_min }),
    ...(filters.tri && { tri: filters.tri }),
    ...(filters.boutique_id && { boutique_id: filters.boutique_id }),
  };

  return useQuery({
    queryKey: ['products', params],
    queryFn: async () => {
      const { data } = await productsAPI.getAll(params);
      return data;
    },
    placeholderData: (prev) => prev, // keep previous data while fetching next page
    staleTime: 1000 * 60 * 2,
    ...options,
  });
}

/**
 * Fetches a single product by id.
 */
export function useProduct(id, options = {}) {
  return useQuery({
    queryKey: ['product', id],
    queryFn: async () => {
      const { data } = await productsAPI.getById(id);
      return data;
    },
    enabled: Boolean(id),
    staleTime: 1000 * 60 * 5,
    ...options,
  });
}
