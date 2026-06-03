import React, { useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, ActivityIndicator,
} from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { colors, spacing, radius, shadow } from '../theme';
import ProductCard from '../components/ProductCard';
import { useCart } from '../contexts/CartContext';

const CATEGORIES = ['Tous', 'Bijoux', 'Céramique', 'Mode', 'Décoration', 'Floral', 'Art', 'Textile', 'Papeterie'];

const MOCK: any[] = [
  { id: 1, nom: 'Vase céramique fait main',    prix: 45,  note_moyenne: 4.8, nb_avis: 24, categorie: 'Céramique', boutique: { nom: 'Céramiques de Lyon' }, image: 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=400&q=70' },
  { id: 2, nom: 'Collier en argent ciselé',    prix: 89,  note_moyenne: 4.9, nb_avis: 18, categorie: 'Bijoux',    boutique: { nom: 'Bijoux Céleste'     }, image: 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=400&q=70' },
  { id: 3, nom: 'Bougie parfumée cire abeille',prix: 18,  note_moyenne: 4.6, nb_avis: 41, categorie: 'Bien-être', boutique: { nom: 'Artisan du Midi'    }, image: 'https://images.unsplash.com/photo-1512572525676-f9b59951929e?w=400&q=70' },
  { id: 4, nom: 'Écharpe laine mérinos',       prix: 65,  note_moyenne: 4.4, nb_avis: 12, categorie: 'Textile',   boutique: { nom: 'Maison Textile'     }, image: 'https://images.unsplash.com/photo-1521488741906-21a748d4d374?w=400&q=70' },
  { id: 5, nom: 'Panier en osier tressé',      prix: 35,  note_moyenne: 4.5, nb_avis: 32, categorie: 'Décoration',boutique: { nom: 'Artisan du Midi'    }, image: 'https://images.unsplash.com/photo-1584464367415-25f25e98d8c8?w=400&q=70' },
  { id: 6, nom: 'Carnet cuir artisanal',       prix: 28,  note_moyenne: 4.7, nb_avis: 15, categorie: 'Art',       boutique: { nom: 'Céramiques de Lyon' }, image: 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=400&q=70' },
];

const SORTS = [
  { label: 'Popularité', value: 'populaire' },
  { label: 'Prix ↑',     value: 'prix_asc'  },
  { label: 'Prix ↓',     value: 'prix_desc' },
  { label: 'Note',       value: 'note'      },
];

export default function CatalogScreen() {
  const navigation = useNavigation<any>();
  const route = useRoute<any>();
  const { addItem } = useCart();

  const [search,   setSearch]   = useState('');
  const [cat,      setCat]      = useState(route.params?.categorie ? CATEGORIES.find(c => c.toLowerCase() === route.params.categorie) ?? 'Tous' : 'Tous');
  const [sort,     setSort]     = useState('populaire');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');

  const filtered = MOCK.filter((p) => {
    if (search && !p.nom.toLowerCase().includes(search.toLowerCase())) return false;
    if (cat !== 'Tous' && p.categorie !== cat) return false;
    if (minPrice && p.prix < Number(minPrice)) return false;
    if (maxPrice && p.prix > Number(maxPrice)) return false;
    return true;
  }).sort((a, b) => {
    if (sort === 'prix_asc')  return a.prix - b.prix;
    if (sort === 'prix_desc') return b.prix - a.prix;
    if (sort === 'note')      return (b.note_moyenne ?? 0) - (a.note_moyenne ?? 0);
    return (b.nb_avis ?? 0) - (a.nb_avis ?? 0);
  });

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
      <FlatList
        data={CATEGORIES}
        horizontal
        showsHorizontalScrollIndicator={false}
        keyExtractor={(c) => c}
        contentContainerStyle={styles.catRow}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={[styles.catChip, cat === item && styles.catChipActive]}
            onPress={() => setCat(item)}
          >
            <Text style={[styles.catChipText, cat === item && styles.catChipTextActive]}>
              {item}
            </Text>
          </TouchableOpacity>
        )}
      />

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
              <Text style={[styles.sortChipText, sort === item.value && styles.sortChipTextActive]}>
                {item.label}
              </Text>
            </TouchableOpacity>
          )}
        />
        <View style={styles.priceRow}>
          <TextInput
            style={styles.priceInput}
            placeholder="Min €"
            keyboardType="numeric"
            value={minPrice}
            onChangeText={setMinPrice}
            placeholderTextColor={colors.gray400}
          />
          <Text style={{ color: colors.gray600, fontSize: 12 }}>—</Text>
          <TextInput
            style={styles.priceInput}
            placeholder="Max €"
            keyboardType="numeric"
            value={maxPrice}
            onChangeText={setMaxPrice}
            placeholderTextColor={colors.gray400}
          />
        </View>
      </View>

      {/* Results */}
      <Text style={styles.resultCount}>{filtered.length} produit{filtered.length > 1 ? 's' : ''}</Text>

      <FlatList
        data={filtered}
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

  catRow: { paddingHorizontal: spacing.md, paddingBottom: spacing.sm, gap: 8 },
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

  list: { padding: spacing.md, paddingTop: 4 },
  row: { gap: 12, marginBottom: 12 },
  cardWrap: { flex: 1 },

  empty: { alignItems: 'center', paddingTop: 60 },
  emptyText: { fontSize: 15, color: colors.gray600 },
});
