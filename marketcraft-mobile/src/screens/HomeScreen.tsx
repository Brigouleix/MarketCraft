import React from 'react';
import {
  View, Text, ScrollView, Image, TouchableOpacity,
  StyleSheet, FlatList, ImageBackground,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { colors, spacing, radius, shadow } from '../theme';
import ProductCard from '../components/ProductCard';
import { useCart } from '../contexts/CartContext';

const HERO_URI = 'https://images.unsplash.com/photo-1452860606245-08befc0ff44b?w=900&q=70';

const CATEGORIES = [
  { label: 'Bijoux',      emoji: '💎', slug: 'bijoux',      bg: '#fef3c7', fg: '#d97706' },
  { label: 'Céramique',   emoji: '☕', slug: 'ceramique',   bg: '#ffedd5', fg: '#ea580c' },
  { label: 'Mode',        emoji: '👕', slug: 'mode',        bg: '#fce7f3', fg: '#db2777' },
  { label: 'Décoration',  emoji: '🏠', slug: 'decoration',  bg: '#dbeafe', fg: '#2563eb' },
  { label: 'Floral',      emoji: '🌸', slug: 'floral',      bg: '#dcfce7', fg: '#16a34a' },
  { label: 'Art',         emoji: '🎨', slug: 'art',         bg: '#f3e8ff', fg: '#9333ea' },
  { label: 'Textile',     emoji: '✂️', slug: 'textile',     bg: '#fee2e2', fg: '#dc2626' },
  { label: 'Papeterie',   emoji: '📖', slug: 'papeterie',   bg: '#ccfbf1', fg: '#0d9488' },
];

const MOCK_PRODUCTS = [
  { id: 1, nom: 'Vase céramique fait main',   prix: 45,  note_moyenne: 4.8, nb_avis: 24, categorie: 'Céramique', boutique: { nom: 'Céramiques de Lyon' }, image: 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=400&q=70' },
  { id: 2, nom: 'Collier en argent ciselé',   prix: 89,  note_moyenne: 4.9, nb_avis: 18, categorie: 'Bijoux',    boutique: { nom: 'Bijoux Céleste'     }, image: 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=400&q=70' },
  { id: 3, nom: 'Bougie parfumée cire abeille',prix: 18, note_moyenne: 4.6, nb_avis: 41, categorie: 'Bien-être', boutique: { nom: 'Artisan du Midi'    }, image: 'https://images.unsplash.com/photo-1512572525676-f9b59951929e?w=400&q=70' },
  { id: 4, nom: 'Écharpe laine mérinos',      prix: 65,  note_moyenne: 4.4, nb_avis: 12, categorie: 'Textile',   boutique: { nom: 'Maison Textile'     }, image: 'https://images.unsplash.com/photo-1521488741906-21a748d4d374?w=400&q=70' },
];

export default function HomeScreen() {
  const navigation = useNavigation<any>();
  const { addItem } = useCart();

  return (
    <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
      {/* Hero */}
      <ImageBackground source={{ uri: HERO_URI }} style={styles.hero}>
        <View style={styles.heroOverlay}>
          <View style={styles.heroTag}>
            <Text style={styles.heroTagText}>🌿 La marketplace des artisans</Text>
          </View>
          <Text style={styles.heroTitle}>
            L'artisanat,{'\n'}<Text style={styles.heroTitleAccent}>réinventé</Text>
          </Text>
          <Text style={styles.heroSub}>
            Créations uniques, fabriquées à la main par des artisans passionnés.
          </Text>
          <View style={styles.heroBtns}>
            <TouchableOpacity
              style={styles.heroBtn}
              onPress={() => navigation.navigate('Catalogue')}
            >
              <Text style={styles.heroBtnText}>Explorer les produits →</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.heroStats}>
            {[['1 200+', 'Artisans'], ['8 500+', 'Produits'], ['4.9★', 'Note']].map(([val, lab]) => (
              <View key={lab} style={styles.heroStat}>
                <Text style={styles.heroStatVal}>{val}</Text>
                <Text style={styles.heroStatLab}>{lab}</Text>
              </View>
            ))}
          </View>
        </View>
      </ImageBackground>

      {/* Categories */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Explorez par catégorie</Text>
        <Text style={styles.sectionSub}>Trouvez l'artisanat qui vous ressemble</Text>
        <View style={styles.catGrid}>
          {CATEGORIES.map((cat) => (
            <TouchableOpacity
              key={cat.slug}
              style={styles.catItem}
              onPress={() => navigation.navigate('Catalogue', { categorie: cat.slug })}
            >
              <View style={[styles.catIcon, { backgroundColor: cat.bg }]}>
                <Text style={{ fontSize: 20 }}>{cat.emoji}</Text>
              </View>
              <Text style={styles.catLabel}>{cat.label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      {/* Trending products */}
      <View style={[styles.section, { backgroundColor: colors.secondary }]}>
        <View style={styles.sectionHeader}>
          <View>
            <Text style={styles.sectionTitle}>Produits tendance</Text>
            <Text style={styles.sectionSub}>Les coups de cœur de la communauté</Text>
          </View>
          <TouchableOpacity onPress={() => navigation.navigate('Catalogue')}>
            <Text style={styles.seeAll}>Voir tout →</Text>
          </TouchableOpacity>
        </View>
        <View style={styles.productsGrid}>
          {MOCK_PRODUCTS.map((product) => (
            <View key={product.id} style={styles.productWrap}>
              <ProductCard
                product={product}
                onPress={() => navigation.navigate('ProductDetail', { productId: product.id })}
                onAddToCart={() => addItem(product)}
              />
            </View>
          ))}
        </View>
      </View>

      {/* Why MarketCraft */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Pourquoi MarketCraft ?</Text>
        {[
          { icon: '🌿', title: 'Artisanat authentique', desc: 'Chaque produit est fabriqué à la main, garantissant une qualité unique.' },
          { icon: '🔒', title: 'Achat sécurisé',        desc: 'Paiements 100% sécurisés, remboursement garanti sous 30 jours.' },
          { icon: '📦', title: 'Livraison soignée',     desc: 'Vos créations emballées avec soin et livrées directement à votre porte.' },
        ].map(({ icon, title, desc }) => (
          <View key={title} style={styles.whyCard}>
            <Text style={styles.whyIcon}>{icon}</Text>
            <View style={{ flex: 1 }}>
              <Text style={styles.whyTitle}>{title}</Text>
              <Text style={styles.whyDesc}>{desc}</Text>
            </View>
          </View>
        ))}
      </View>

      {/* CTA */}
      <View style={styles.cta}>
        <Text style={styles.ctaStar}>★</Text>
        <Text style={styles.ctaTitle}>Vous êtes artisan ?</Text>
        <Text style={styles.ctaSub}>
          Rejoignez notre communauté et vendez vos créations. Boutique gratuite, commission réduite.
        </Text>
        <TouchableOpacity
          style={styles.ctaBtn}
          onPress={() => navigation.navigate('Register')}
        >
          <Text style={styles.ctaBtnText}>Créer ma boutique →</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },

  // Hero
  hero: { width: '100%', minHeight: 420 },
  heroOverlay: {
    flex: 1, minHeight: 420,
    backgroundColor: 'rgba(0,0,0,0.55)',
    padding: spacing.lg, paddingTop: spacing.xxl,
    alignItems: 'center', justifyContent: 'center',
  },
  heroTag: {
    backgroundColor: 'rgba(255,255,255,0.15)',
    borderWidth: 1, borderColor: 'rgba(255,255,255,0.25)',
    paddingHorizontal: 16, paddingVertical: 6,
    borderRadius: radius.full, marginBottom: spacing.md,
  },
  heroTagText: { color: colors.white, fontSize: 12 },
  heroTitle: {
    fontSize: 42, fontWeight: '700', color: colors.white,
    textAlign: 'center', lineHeight: 50, marginBottom: spacing.sm,
  },
  heroTitleAccent: { color: colors.amber },
  heroSub: {
    fontSize: 15, color: 'rgba(255,255,255,0.85)',
    textAlign: 'center', marginBottom: spacing.lg, lineHeight: 22,
  },
  heroBtns: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.xl },
  heroBtn: {
    backgroundColor: colors.primary, paddingHorizontal: 28, paddingVertical: 13,
    borderRadius: radius.md,
  },
  heroBtnText: { color: colors.white, fontWeight: '700', fontSize: 14 },
  heroStats: { flexDirection: 'row', gap: spacing.xl },
  heroStat: { alignItems: 'center' },
  heroStatVal: { fontSize: 20, fontWeight: '700', color: colors.white },
  heroStatLab: { fontSize: 11, color: 'rgba(255,255,255,0.6)', marginTop: 2 },

  // Section
  section: { backgroundColor: colors.white, padding: spacing.lg },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: spacing.md },
  sectionTitle: { fontSize: 22, fontWeight: '700', color: colors.primary, marginBottom: 4 },
  sectionSub: { fontSize: 13, color: colors.gray600, marginBottom: spacing.md },
  seeAll: { fontSize: 13, fontWeight: '600', color: colors.primary },

  // Categories
  catGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  catItem: { width: '21%', alignItems: 'center', marginBottom: spacing.sm },
  catIcon: {
    width: 52, height: 52, borderRadius: radius.md,
    alignItems: 'center', justifyContent: 'center', marginBottom: 6,
    ...shadow.craft,
  },
  catLabel: { fontSize: 11, fontWeight: '500', color: colors.gray800, textAlign: 'center' },

  // Products
  productsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  productWrap: { width: '47%' },

  // Why
  whyCard: {
    flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md,
    backgroundColor: colors.white, borderRadius: radius.lg, padding: spacing.md,
    marginBottom: spacing.sm, ...shadow.craft,
    borderWidth: 1, borderColor: colors.secondary300,
  },
  whyIcon: { fontSize: 28, marginTop: 2 },
  whyTitle: { fontSize: 15, fontWeight: '700', color: colors.gray800, marginBottom: 4 },
  whyDesc: { fontSize: 13, color: colors.gray600, lineHeight: 18 },

  // CTA
  cta: {
    backgroundColor: colors.primary, padding: spacing.xl,
    alignItems: 'center',
  },
  ctaStar: { fontSize: 32, color: colors.amber, marginBottom: spacing.sm },
  ctaTitle: { fontSize: 26, fontWeight: '700', color: colors.white, marginBottom: spacing.sm, textAlign: 'center' },
  ctaSub: { fontSize: 14, color: 'rgba(255,255,255,0.75)', textAlign: 'center', marginBottom: spacing.lg, lineHeight: 20 },
  ctaBtn: {
    backgroundColor: colors.white, paddingHorizontal: 32, paddingVertical: 13,
    borderRadius: radius.md, ...shadow.craftLg,
  },
  ctaBtnText: { color: colors.primary, fontWeight: '700', fontSize: 15 },
});
