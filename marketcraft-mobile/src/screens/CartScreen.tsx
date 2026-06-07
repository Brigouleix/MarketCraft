import React from 'react';
import {
  View, Text, FlatList, TouchableOpacity, Image,
  StyleSheet, Alert,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { colors, spacing, radius, shadow } from '../theme';
import { useCart } from '../contexts/CartContext';

export default function CartScreen() {
  const navigation = useNavigation<any>();
  const { items, total, removeItem, updateQty, clearCart } = useCart();

  const handleCheckout = () => {
    if (items.length === 0) return;
    navigation.navigate('Checkout');
  };

  if (items.length === 0) {
    return (
      <View style={styles.empty}>
        <Text style={styles.emptyIcon}>🛒</Text>
        <Text style={styles.emptyTitle}>Votre panier est vide</Text>
        <Text style={styles.emptySub}>Explorez notre catalogue pour trouver votre bonheur.</Text>
        <TouchableOpacity style={styles.emptyBtn} onPress={() => navigation.navigate('Catalogue')}>
          <Text style={styles.emptyBtnText}>Explorer les produits</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={items}
        keyExtractor={(i) => String(i.id)}
        contentContainerStyle={styles.list}
        ItemSeparatorComponent={() => <View style={styles.sep} />}
        renderItem={({ item }) => (
          <View style={styles.item}>
            <Image
              source={{ uri: item.image || 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=200&q=60' }}
              style={styles.thumb}
              resizeMode="cover"
            />
            <View style={styles.itemBody}>
              <Text style={styles.itemName} numberOfLines={2}>{item.nom}</Text>
              {item.boutique && <Text style={styles.itemShop}>{item.boutique.nom}</Text>}
              <Text style={styles.itemPrice}>{item.prix} €</Text>
              <View style={styles.qtyRow}>
                <TouchableOpacity style={styles.qtyBtn} onPress={() => updateQty(item.id, item.quantite - 1)}>
                  <Text style={styles.qtyBtnText}>−</Text>
                </TouchableOpacity>
                <Text style={styles.qtyVal}>{item.quantite}</Text>
                <TouchableOpacity style={styles.qtyBtn} onPress={() => updateQty(item.id, item.quantite + 1)}>
                  <Text style={styles.qtyBtnText}>+</Text>
                </TouchableOpacity>
              </View>
            </View>
            <View style={styles.itemRight}>
              <Text style={styles.itemTotal}>{(item.prix * item.quantite).toFixed(0)} €</Text>
              <TouchableOpacity onPress={() => removeItem(item.id)}>
                <Text style={styles.removeBtn}>🗑</Text>
              </TouchableOpacity>
            </View>
          </View>
        )}
        ListFooterComponent={
          <TouchableOpacity onPress={() => Alert.alert('Vider', 'Vider le panier ?', [
            { text: 'Annuler', style: 'cancel' },
            { text: 'Vider', style: 'destructive', onPress: clearCart },
          ])}>
            <Text style={styles.clearBtn}>Vider le panier</Text>
          </TouchableOpacity>
        }
      />

      {/* Bottom summary */}
      <View style={styles.summary}>
        <View style={styles.summaryRow}>
          <Text style={styles.summaryLabel}>Sous-total</Text>
          <Text style={styles.summaryVal}>{total.toFixed(2)} €</Text>
        </View>
        <View style={styles.summaryRow}>
          <Text style={styles.summaryLabel}>Livraison</Text>
          <Text style={[styles.summaryVal, { color: colors.accent }]}>Gratuite</Text>
        </View>
        <View style={[styles.summaryRow, styles.totalRow]}>
          <Text style={styles.totalLabel}>Total</Text>
          <Text style={styles.totalVal}>{total.toFixed(2)} €</Text>
        </View>
        <TouchableOpacity style={styles.checkoutBtn} onPress={handleCheckout}>
          <Text style={styles.checkoutBtnText}>Commander →</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },
  list: { padding: spacing.md },
  sep: { height: 12 },

  item: {
    flexDirection: 'row', backgroundColor: colors.white,
    borderRadius: radius.md, overflow: 'hidden',
    borderWidth: 1, borderColor: colors.secondary300,
    ...shadow.craft,
  },
  thumb: { width: 90, height: 90 },
  itemBody: { flex: 1, padding: spacing.sm },
  itemName: { fontSize: 13, fontWeight: '600', color: colors.gray800, marginBottom: 2 },
  itemShop: { fontSize: 11, color: colors.gray600, marginBottom: 4 },
  itemPrice: { fontSize: 14, fontWeight: '700', color: colors.primary, marginBottom: 8 },
  qtyRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  qtyBtn: {
    width: 28, height: 28, borderRadius: 6,
    backgroundColor: colors.secondary, borderWidth: 1, borderColor: colors.secondary400,
    alignItems: 'center', justifyContent: 'center',
  },
  qtyBtnText: { fontSize: 16, fontWeight: '600', color: colors.gray800 },
  qtyVal: { fontSize: 15, fontWeight: '700', color: colors.gray800, minWidth: 20, textAlign: 'center' },
  itemRight: { padding: spacing.sm, alignItems: 'flex-end', justifyContent: 'space-between' },
  itemTotal: { fontSize: 16, fontWeight: '700', color: colors.primary },
  removeBtn: { fontSize: 20 },

  clearBtn: { textAlign: 'center', color: colors.red, fontSize: 13, fontWeight: '600', marginTop: spacing.md },

  summary: {
    backgroundColor: colors.white, padding: spacing.lg,
    borderTopWidth: 1, borderTopColor: colors.secondary300,
    ...shadow.craftLg,
  },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  summaryLabel: { fontSize: 14, color: colors.gray600 },
  summaryVal: { fontSize: 14, fontWeight: '600', color: colors.gray800 },
  totalRow: { borderTopWidth: 1, borderTopColor: colors.secondary300, paddingTop: 10, marginTop: 4, marginBottom: spacing.md },
  totalLabel: { fontSize: 16, fontWeight: '700', color: colors.gray800 },
  totalVal: { fontSize: 20, fontWeight: '700', color: colors.primary },
  checkoutBtn: {
    backgroundColor: colors.primary, paddingVertical: 14,
    borderRadius: radius.md, alignItems: 'center',
    ...shadow.craftLg,
  },
  checkoutBtnText: { color: colors.white, fontWeight: '700', fontSize: 16 },

  empty: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.xl },
  emptyIcon: { fontSize: 60, marginBottom: spacing.md },
  emptyTitle: { fontSize: 20, fontWeight: '700', color: colors.gray800, marginBottom: 8 },
  emptySub: { fontSize: 14, color: colors.gray600, textAlign: 'center', marginBottom: spacing.lg },
  emptyBtn: {
    backgroundColor: colors.primary, paddingHorizontal: 28, paddingVertical: 12,
    borderRadius: radius.md,
  },
  emptyBtnText: { color: colors.white, fontWeight: '700', fontSize: 14 },
});
