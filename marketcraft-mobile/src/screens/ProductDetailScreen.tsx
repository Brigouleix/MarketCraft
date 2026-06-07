import React, { useState } from 'react';
import {
  View, Text, ScrollView, Image, TouchableOpacity,
  StyleSheet, Alert, TextInput,
} from 'react-native';
import { useRoute, useNavigation } from '@react-navigation/native';
import { colors, spacing, radius, shadow } from '../theme';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';

const MOCK_PRODUCT = {
  id: 1,
  nom: 'Vase céramique fait main',
  prix: 45,
  description: 'Un vase en céramique fabriqué à la main dans notre atelier de Lyon. Chaque pièce est unique, émaillée avec des pigments naturels et cuite au four traditionnel. Dimensions : Ø 12cm × H 24cm. Convient pour fleurs coupées ou en décoration seule.',
  categorie: 'Céramique',
  stock: 8,
  note_moyenne: 4.8,
  nb_avis: 24,
  boutique: { id: 1, nom: 'Céramiques de Lyon' },
  images: [
    'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=600&q=80',
    'https://images.unsplash.com/photo-1481349518771-20055b2a7b24?w=600&q=80',
  ],
  avis: [
    { id: 1, user: { prenom: 'Sophie' }, note: 5, commentaire: 'Magnifique, exactement comme sur la photo !', created_at: '2026-05-15' },
    { id: 2, user: { prenom: 'Paul'   }, note: 4, commentaire: 'Très belle qualité, livraison soignée.', created_at: '2026-05-02' },
  ],
};

export default function ProductDetailScreen() {
  const route = useRoute<any>();
  const navigation = useNavigation<any>();
  const { addItem } = useCart();
  const { isAuthenticated } = useAuth();

  const product = MOCK_PRODUCT; // remplacer par appel API avec route.params.productId
  const [qty, setQty]           = useState(1);
  const [imgIndex, setImgIndex] = useState(0);
  const [comment, setComment]   = useState('');
  const [rating, setRating]     = useState(5);

  const handleAdd = () => {
    addItem(product, qty);
    Alert.alert('Panier', `${product.nom} ajouté au panier ✓`);
  };

  const stars = (n: number) => '★'.repeat(n) + '☆'.repeat(5 - n);

  return (
    <View style={styles.container}>
      <ScrollView showsVerticalScrollIndicator={false}>
        {/* Image */}
        <Image source={{ uri: product.images[imgIndex] }} style={styles.mainImage} resizeMode="cover" />
        <View style={styles.thumbnails}>
          {product.images.map((uri, i) => (
            <TouchableOpacity key={i} onPress={() => setImgIndex(i)}>
              <Image
                source={{ uri }}
                style={[styles.thumb, imgIndex === i && styles.thumbActive]}
                resizeMode="cover"
              />
            </TouchableOpacity>
          ))}
        </View>

        <View style={styles.body}>
          {/* Header */}
          <Text style={styles.cat}>{product.categorie.toUpperCase()}</Text>
          <Text style={styles.name}>{product.nom}</Text>
          <TouchableOpacity onPress={() => navigation.navigate('Boutique', { boutiqueId: product.boutique.id })}>
            <Text style={styles.shop}>par {product.boutique.nom} →</Text>
          </TouchableOpacity>

          <View style={styles.ratingRow}>
            <Text style={styles.stars}>{stars(Math.round(product.note_moyenne))}</Text>
            <Text style={styles.ratingText}>{product.note_moyenne.toFixed(1)} ({product.nb_avis} avis)</Text>
          </View>

          <Text style={styles.price}>{product.prix} €</Text>
          <Text style={styles.stock}>
            {product.stock > 0 ? `✓ En stock (${product.stock} disponibles)` : '✗ Rupture de stock'}
          </Text>

          {/* Qty */}
          <View style={styles.qtyRow}>
            <TouchableOpacity style={styles.qtyBtn} onPress={() => setQty(Math.max(1, qty - 1))}>
              <Text style={styles.qtyBtnText}>−</Text>
            </TouchableOpacity>
            <Text style={styles.qtyVal}>{qty}</Text>
            <TouchableOpacity style={styles.qtyBtn} onPress={() => setQty(Math.min(product.stock, qty + 1))}>
              <Text style={styles.qtyBtnText}>+</Text>
            </TouchableOpacity>
          </View>

          <TouchableOpacity style={styles.addBtn} onPress={handleAdd}>
            <Text style={styles.addBtnText}>Ajouter au panier — {product.prix * qty} €</Text>
          </TouchableOpacity>

          {/* Description */}
          <Text style={styles.descTitle}>Description</Text>
          <Text style={styles.desc}>{product.description}</Text>

          {/* Avis */}
          <Text style={styles.descTitle}>Avis clients ({product.nb_avis})</Text>
          {product.avis.map((avis) => (
            <View key={avis.id} style={styles.avisCard}>
              <View style={styles.avisHeader}>
                <View style={styles.avisAvatar}>
                  <Text style={styles.avisAvatarText}>{avis.user.prenom[0]}</Text>
                </View>
                <View>
                  <Text style={styles.avisUser}>{avis.user.prenom}</Text>
                  <Text style={styles.avisStars}>{stars(avis.note)}</Text>
                </View>
                <Text style={styles.avisDate}>{avis.created_at}</Text>
              </View>
              <Text style={styles.avisComment}>{avis.commentaire}</Text>
            </View>
          ))}

          {/* Post avis */}
          {isAuthenticated && (
            <View style={styles.postAvis}>
              <Text style={styles.descTitle}>Laisser un avis</Text>
              <View style={styles.starPicker}>
                {[1,2,3,4,5].map((n) => (
                  <TouchableOpacity key={n} onPress={() => setRating(n)}>
                    <Text style={[styles.starPickerStar, n <= rating && styles.starPickerActive]}>★</Text>
                  </TouchableOpacity>
                ))}
              </View>
              <TextInput
                style={styles.commentInput}
                placeholder="Partagez votre expérience…"
                placeholderTextColor={colors.gray400}
                multiline
                numberOfLines={3}
                value={comment}
                onChangeText={setComment}
              />
              <TouchableOpacity style={styles.submitBtn}>
                <Text style={styles.submitBtnText}>Publier l'avis</Text>
              </TouchableOpacity>
            </View>
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.white },
  mainImage: { width: '100%', height: 300 },
  thumbnails: { flexDirection: 'row', gap: 8, padding: spacing.md },
  thumb: { width: 60, height: 60, borderRadius: radius.sm, borderWidth: 1, borderColor: colors.secondary300 },
  thumbActive: { borderColor: colors.primary, borderWidth: 2 },

  body: { padding: spacing.md },
  cat: { fontSize: 11, fontWeight: '700', color: colors.accent, letterSpacing: 0.6, marginBottom: 4 },
  name: { fontSize: 22, fontWeight: '700', color: colors.gray800, marginBottom: 4 },
  shop: { fontSize: 13, color: colors.primary, fontWeight: '600', marginBottom: spacing.sm },
  ratingRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: spacing.sm },
  stars: { color: colors.amber, fontSize: 16 },
  ratingText: { fontSize: 13, color: colors.gray600 },
  price: { fontSize: 30, fontWeight: '700', color: colors.primary, marginBottom: 4 },
  stock: { fontSize: 13, color: colors.accent, marginBottom: spacing.md },

  qtyRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, marginBottom: spacing.md },
  qtyBtn: {
    width: 36, height: 36, borderRadius: radius.sm,
    backgroundColor: colors.secondary, borderWidth: 1, borderColor: colors.secondary400,
    alignItems: 'center', justifyContent: 'center',
  },
  qtyBtnText: { fontSize: 20, fontWeight: '600', color: colors.gray800 },
  qtyVal: { fontSize: 18, fontWeight: '700', color: colors.gray800, minWidth: 24, textAlign: 'center' },

  addBtn: {
    backgroundColor: colors.primary, paddingVertical: 15,
    borderRadius: radius.md, alignItems: 'center', marginBottom: spacing.lg,
    ...shadow.craftLg,
  },
  addBtnText: { color: colors.white, fontWeight: '700', fontSize: 16 },

  descTitle: { fontSize: 17, fontWeight: '700', color: colors.gray800, marginBottom: spacing.sm, marginTop: spacing.md },
  desc: { fontSize: 14, color: colors.gray600, lineHeight: 22, marginBottom: spacing.lg },

  avisCard: {
    backgroundColor: colors.secondary, borderRadius: radius.md,
    padding: spacing.md, marginBottom: spacing.sm,
    borderWidth: 1, borderColor: colors.secondary300,
  },
  avisHeader: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginBottom: 8 },
  avisAvatar: {
    width: 32, height: 32, borderRadius: radius.full,
    backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center',
  },
  avisAvatarText: { color: colors.white, fontWeight: '700', fontSize: 14 },
  avisUser: { fontSize: 13, fontWeight: '700', color: colors.gray800 },
  avisStars: { color: colors.amber, fontSize: 12 },
  avisDate: { fontSize: 11, color: colors.gray400, marginLeft: 'auto' },
  avisComment: { fontSize: 13, color: colors.gray600, lineHeight: 18 },

  postAvis: {
    borderTopWidth: 1, borderTopColor: colors.secondary300,
    paddingTop: spacing.md, marginTop: spacing.md,
  },
  starPicker: { flexDirection: 'row', gap: 8, marginBottom: spacing.sm },
  starPickerStar: { fontSize: 28, color: colors.secondary400 },
  starPickerActive: { color: colors.amber },
  commentInput: {
    borderWidth: 1, borderColor: colors.secondary400, borderRadius: radius.md,
    padding: spacing.sm, fontSize: 14, color: colors.gray800,
    minHeight: 80, textAlignVertical: 'top', marginBottom: spacing.sm,
  },
  submitBtn: {
    backgroundColor: colors.accent, paddingVertical: 12,
    borderRadius: radius.md, alignItems: 'center',
  },
  submitBtnText: { color: colors.white, fontWeight: '700', fontSize: 14 },
});
