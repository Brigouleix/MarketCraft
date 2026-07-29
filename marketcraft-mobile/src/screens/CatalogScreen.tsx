import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, ActivityIndicator,
} from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { colors, spacing, radius } from '../theme';
import ProductCard from '../components/ProductCard';
import { useCart } from '../contexts/CartContext';
import { productsAPI } from '../services/api';

// Catégories « objet » de la taxonomie réelle (racine « Objet »).
const CATEGORIES: { label: string; slug: string | null }[] = [
  { label: 'Tous',        slug: null },
  { label: 'Bijoux',      slug: 'bijoux' },
  { label: 'Textile',     slug: 'textile' },
  { label: 'Déco',        slug: 'decoration-maison' },
  { label: 'Menuiserie',  slug: 'menuiserie' },
  { label: 'Poterie',     slug: 'poterie' },
  { label: 'Accessoires', slug: 'accessoires' },
  { label: 'Couture',     slug: 'couture' },
];

const SORTS = [
  { label: 'Popularité', value: 'populaire' },
  { label: 'Prix ↑',     value: 'prix_asc'  },
  { label: 'Prix ↓',     value: 'prix_desc' },
  { label: 'Note',       value: 'note'      },
];

const isUrl = (s: unknown): s is string => typeof s === 'string' && /^https?:\/\//.test(s);

// Normalise un produit de l'API vers la forme attendue par ProductCard.
function toCard(p: any) {
  let imgs: any[] = [];
  if (Array.isArray(p.images)) imgs = p.images;
  else if (typeof p.images === 'string') { try { imgs = JSON.parse(p.images) || []; } catch { imgs = []; } }
  const first = imgs[0];
  return {
    id: p.id,
    nom: p.nom,
    prix: Number(p.prix),
    // On ne passe que des URL exploitables ; sinon ProductCard affiche son
    // visuel de remplacement (les images de démo sont de simples noms de fichier).
    image: isUrl(first) ? first : (isUrl(p.image) ? p.image : undefined),
    categorie: typeof p.categorie === 'object' ? p.categorie?.nom : p.categorie,
    note_moyenne: Number(p.note_moyenne ?? 0),
    nb_avis: p.nb_avis ?? p.nombre_avis ?? 0,
    boutique: p.boutique,
  };
}

export default function CatalogScreen() {
  const navigation = useNavigation<any>();
  const route = useRoute<any>();
  const { addItem } = useCart();

  const [search,   setSearch]   = useState('');
  const [catSlug,  setCatSlug]  = useState<string | null>(route.params?.categorie ?? null);
  const [sort,     setSort]     = useState('populaire');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');

  const [products, setProducts] = useState<any[]>([]);
  const [loading,  setLoading]  = useState(true);

  const fetchProducts = useCallback(async () => {
    setLoading(true);
    try {
      const params: any = { limit: 50 };
      if (search.trim()) params.search = search.trim();
      if (catSlug) params.categorie = catSlug;
      if (minPrice) params.prix_min = minPrice;
      if (maxPrice) params.prix_max = maxPrice;
      if (sort === 'note') { params.sort = 'note'; params.order = 'desc'; }
      else params.tri = sort;

      const res = await productsAPI.getAll(params);
      const list = res.data?.data ?? res.data ?? [];
      setProducts(Array.isArray(list) ? list.map(toCard) : []);
    } catch {
      setProducts([]);
    } finally {
      setLoading(false);
    }
  }, [search, catSlug, sort, minPrice, maxPrice]);

  // Débounce léger : on ne rappelle l'API qu'après 350 ms sans frappe.
  useEffect(() => {
    const t = setTimeout(fetchProducts, 350);
    return () => clearTimeout(t);
  }, [fetchProducts]);

  const renderItem = useCallback(({ item }: { item: any }) => (
    <View style={styles.cardWrap}>
      <ProductCard
        product={item}
        onPress={() => navigation.navigate('ProductDetail', { productId: item.id })}
        onAddToCart={() => addItem(item)}
      />
    </View>
  ), [navigation, addItem]);

  return (
    <View style={styles.container}>
      {/* Search bar */}
      <View style={styles.searchRow}>
        <View style={styles.searchBox}>
          <Text style={styles.searchIcon}>🔍</Text>
          <TextInput
            style={styles.searchInput}
            placeholder="Rechercher un produit…"
            placeholderTextColor={colors.gray400}
            value={search}
            onChangeText={setSearch}
          />
        </View>
      </View>

      {/* Category chips */}
      <View style={styles.chipsWrap}>
        <FlatList
          data={CATEGORIES}
          horizontal
          showsHorizontalScrollIndicator={false}
          keyExtractor={(c) => c.label}
          contentContainerStyle={styles.catRow}
          renderItem={({ item }) => {
            const active = catSlug === item.slug;
            return (
              <TouchableOpacity
                style={[styles.catChip, active && styles.catChipActive]}
                onPress={() => setCatSlug(item.slug)}
              >
                <Text style={[styles.catChipText, active && styles.catChipTextActive]}>{item.label}</Text>
              </TouchableOpacity>
            );
          }}
        />
      </View>

      {/* Sort + price filters */}
      <View style={styles.filtersRow}>
        <FlatList
          data={SORTS}
          horizontal
          showsHorizontalScrollIndicator={false}
          keyExtractor={(s) => s.value}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={[styles.sortChip, sort === item.value && styles.sortChipActive]}
              onPress={() => setSort(item.value)}
            >
              <Text style={[styles.sortChipText, sort === item.value && styles.sortChipTextActive]}>{item.label}</Text>
            </TouchableOpacity>
          )}
        />
        <View style={styles.priceRow}>
          <TextInput style={styles.priceInput} placeholder="Min €" keyboardType="numeric"
            value={minPrice} onChangeText={setMinPrice} placeholderTextColor={colors.gray400} />
          <Text style={{ color: colors.gray600, fontSize: 12 }}>—</Text>
          <TextInput style={styles.priceInput} placeholder="Max €" keyboardType="numeric"
            value={maxPrice} onChangeText={setMaxPrice} placeholderTextColor={colors.gray400} />
        </View>
      </View>

      {/* Results */}
      {loading ? (
        <View style={styles.loadingWrap}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <>
          <Text style={styles.resultCount}>{products.length} produit{products.length > 1 ? 's' : ''}</Text>
          <FlatList
            data={products}
            renderItem={renderItem}
            keyExtractor={(p) => String(p.id)}
            numColumns={2}
            columnWrapperStyle={styles.row}
            contentContainerStyle={styles.list}
            showsVerticalScrollIndicator={false}
            ListEmptyComponent={
              <View style={styles.empty}>
                <Text style={styles.emptyText}>Aucun produit trouvé</Text>
              </View>
            }
          />
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },

  searchRow: { paddingHorizontal: spacing.md, paddingTop: spacing.md, paddingBottom: spacing.sm },
  searchBox: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: colors.white, borderRadius: radius.md,
    borderWidth: 1, borderColor: colors.secondary400,
    paddingHorizontal: spacing.sm,
  },
  searchIcon: { fontSize: 14, marginRight: 6 },
  searchInput: { flex: 1, paddingVertical: 10, fontSize: 14, color: colors.gray800 },

  chipsWrap: { paddingBottom: spacing.sm },
  catRow: { paddingHorizontal: spacing.md, gap: 8 },
  catChip: {
    paddingHorizontal: 14, paddingVertical: 7, borderRadius: radius.full,
    backgroundColor: colors.white, borderWidth: 1, borderColor: colors.secondary400,
  },
  catChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  catChipText: { fontSize: 13, fontWeight: '500', color: colors.gray800 },
  catChipTextActive: { color: colors.white },

  filtersRow: { paddingHorizontal: spacing.md, paddingBottom: spacing.sm },
  sortChip: {
    paddingHorizontal: 12, paddingVertical: 6, borderRadius: radius.sm,
    backgroundColor: colors.white, borderWidth: 1, borderColor: colors.secondary400,
    marginRight: 6,
  },
  sortChipActive: { backgroundColor: colors.primaryLight, borderColor: colors.primary },
  sortChipText: { fontSize: 12, color: colors.gray600 },
  sortChipTextActive: { color: colors.primary, fontWeight: '700' },
  priceRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 8 },
  priceInput: {
    flex: 1, paddingVertical: 7, paddingHorizontal: 10,
    borderWidth: 1, borderColor: colors.secondary400, borderRadius: radius.sm,
    fontSize: 12, color: colors.gray800, backgroundColor: colors.white,
  },

  resultCount: { paddingHorizontal: spacing.md, fontSize: 12, color: colors.gray600, marginBottom: 4 },
  loadingWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },

  list: { padding: spacing.md, paddingTop: 4 },
  row: { gap: 12, marginBottom: 12 },
  cardWrap: { flex: 1 },

  empty: { alignItems: 'center', paddingTop: 60 },
  emptyText: { fontSize: 15, color: colors.gray600 },
});
