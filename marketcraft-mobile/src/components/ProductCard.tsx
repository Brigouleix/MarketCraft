import React from 'react';
import { View, Text, Image, TouchableOpacity, StyleSheet } from 'react-native';
import { colors, radius, shadow, spacing } from '../theme';

interface Props {
  product: {
    id: number;
    nom: string;
    prix: number;
    image?: string;
    categorie?: string;
    note_moyenne?: number;
    nb_avis?: number;
    boutique?: { nom: string };
  };
  onPress: () => void;
  onAddToCart: () => void;
}

const PLACEHOLDER = 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=400&q=70';

export default function ProductCard({ product, onPress, onAddToCart }: Props) {
  const stars = Math.round(product.note_moyenne ?? 0);

  return (
    <TouchableOpacity style={styles.card} onPress={onPress} activeOpacity={0.85}>
      <Image
        source={{ uri: product.image || PLACEHOLDER }}
        style={styles.image}
        resizeMode="cover"
      />
      <View style={styles.body}>
        {product.categorie && (
          <Text style={styles.cat}>{product.categorie.toUpperCase()}</Text>
        )}
        <Text style={styles.name} numberOfLines={2}>{product.nom}</Text>
        {product.boutique && (
          <Text style={styles.shop} numberOfLines={1}>{product.boutique.nom}</Text>
        )}
        {product.note_moyenne != null && (
          <Text style={styles.rating}>
            {'★'.repeat(stars)}{'☆'.repeat(5 - stars)}
            {'  '}{product.note_moyenne.toFixed(1)}
            {product.nb_avis != null ? ` (${product.nb_avis})` : ''}
          </Text>
        )}
        <View style={styles.footer}>
          <Text style={styles.price}>{product.prix} €</Text>
          <TouchableOpacity style={styles.addBtn} onPress={onAddToCart}>
            <Text style={styles.addBtnText}>+ Panier</Text>
          </TouchableOpacity>
        </View>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.secondary300,
    overflow: 'hidden',
    flex: 1,
    ...shadow.craft,
  },
  image: {
    width: '100%',
    aspectRatio: 1,
  },
  body: {
    padding: spacing.sm + 2,
  },
  cat: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.accent,
    letterSpacing: 0.6,
    marginBottom: 3,
  },
  name: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.gray800,
    lineHeight: 18,
    marginBottom: 3,
  },
  shop: {
    fontSize: 11,
    color: colors.gray600,
    marginBottom: 5,
  },
  rating: {
    fontSize: 11,
    color: colors.amber,
    marginBottom: 8,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  price: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.primary,
  },
  addBtn: {
    backgroundColor: colors.primary,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: radius.sm,
  },
  addBtnText: {
    color: colors.white,
    fontSize: 11,
    fontWeight: '700',
  },
});
