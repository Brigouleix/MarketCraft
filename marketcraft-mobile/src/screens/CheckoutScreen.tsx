import React, { useState } from 'react';
import {
  View, Text, ScrollView, TextInput, TouchableOpacity,
  StyleSheet, Alert, ActivityIndicator,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { colors, spacing, radius, shadow } from '../theme';
import { useCart } from '../contexts/CartContext';
import { ordersAPI } from '../services/api';

interface Address {
  prenom: string; nom: string; adresse: string; cp: string; ville: string;
}

export default function CheckoutScreen() {
  const navigation = useNavigation<any>();
  const { items, total, clearCart } = useCart();
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState<Address>({ prenom: '', nom: '', adresse: '', cp: '', ville: '' });

  const update = (key: keyof Address) => (val: string) => setForm((f) => ({ ...f, [key]: val }));

  const handleOrder = async () => {
    const { prenom, nom, adresse, cp, ville } = form;
    if (!prenom || !nom || !adresse || !cp || !ville) {
      Alert.alert('Erreur', 'Merci de remplir tous les champs.'); return;
    }
    setLoading(true);
    try {
      await ordersAPI.create({
        adresse_livraison: `${prenom} ${nom}, ${adresse}, ${cp} ${ville}`,
        items: items.map((i) => ({ product_id: i.id, quantite: i.quantite })),
      });
      clearCart();
      Alert.alert('Commande confirmée ✓', 'Votre commande a bien été passée !', [
        { text: 'OK', onPress: () => navigation.navigate('Home') },
      ]);
    } catch {
      Alert.alert('Erreur', 'Impossible de passer la commande. Réessayez.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
      <View style={styles.section}>
        <Text style={styles.title}>Adresse de livraison</Text>

        <View style={styles.row}>
          <View style={[styles.fieldWrap, { flex: 1 }]}>
            <Text style={styles.label}>Prénom</Text>
            <TextInput style={styles.input} placeholder="Jean" placeholderTextColor={colors.gray400} value={form.prenom} onChangeText={update('prenom')} />
          </View>
          <View style={[styles.fieldWrap, { flex: 1 }]}>
            <Text style={styles.label}>Nom</Text>
            <TextInput style={styles.input} placeholder="Dupont" placeholderTextColor={colors.gray400} value={form.nom} onChangeText={update('nom')} />
          </View>
        </View>

        <View style={styles.fieldWrap}>
          <Text style={styles.label}>Adresse</Text>
          <TextInput style={styles.input} placeholder="12 rue de la Paix" placeholderTextColor={colors.gray400} value={form.adresse} onChangeText={update('adresse')} />
        </View>

        <View style={styles.row}>
          <View style={[styles.fieldWrap, { width: 110 }]}>
            <Text style={styles.label}>Code postal</Text>
            <TextInput style={styles.input} placeholder="75001" keyboardType="numeric" placeholderTextColor={colors.gray400} value={form.cp} onChangeText={update('cp')} />
          </View>
          <View style={[styles.fieldWrap, { flex: 1 }]}>
            <Text style={styles.label}>Ville</Text>
            <TextInput style={styles.input} placeholder="Paris" placeholderTextColor={colors.gray400} value={form.ville} onChangeText={update('ville')} />
          </View>
        </View>
      </View>

      {/* Order summary */}
      <View style={styles.section}>
        <Text style={styles.title}>Récapitulatif</Text>
        {items.map((item) => (
          <View key={item.id} style={styles.summaryLine}>
            <Text style={styles.summaryName} numberOfLines={1}>{item.nom} × {item.quantite}</Text>
            <Text style={styles.summaryVal}>{(item.prix * item.quantite).toFixed(0)} €</Text>
          </View>
        ))}
        <View style={styles.sep} />
        <View style={styles.summaryLine}>
          <Text style={styles.summaryLabel}>Livraison</Text>
          <Text style={[styles.summaryVal, { color: colors.accent }]}>Gratuite</Text>
        </View>
        <View style={[styles.summaryLine, { marginTop: 8 }]}>
          <Text style={styles.totalLabel}>Total</Text>
          <Text style={styles.totalVal}>{total.toFixed(2)} €</Text>
        </View>
      </View>

      <View style={styles.section}>
        <TouchableOpacity
          style={[styles.orderBtn, loading && styles.orderBtnDisabled]}
          onPress={handleOrder}
          disabled={loading}
        >
          {loading
            ? <ActivityIndicator color={colors.white} />
            : <Text style={styles.orderBtnText}>Confirmer la commande ✓</Text>
          }
        </TouchableOpacity>
        <Text style={styles.secureNote}>🔒 Paiement 100% sécurisé</Text>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },
  section: {
    backgroundColor: colors.white, margin: spacing.md,
    borderRadius: radius.lg, padding: spacing.md,
    borderWidth: 1, borderColor: colors.secondary300,
    ...shadow.craft,
  },
  title: { fontSize: 18, fontWeight: '700', color: colors.primary, marginBottom: spacing.md },
  row: { flexDirection: 'row', gap: 12 },
  fieldWrap: { marginBottom: spacing.sm },
  label: { fontSize: 13, fontWeight: '600', color: colors.gray800, marginBottom: 6 },
  input: {
    borderWidth: 1.5, borderColor: colors.secondary400,
    borderRadius: radius.sm, paddingHorizontal: 12, paddingVertical: 10,
    fontSize: 14, color: colors.gray800, backgroundColor: colors.white,
  },
  summaryLine: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  summaryName: { fontSize: 13, color: colors.gray800, flex: 1, marginRight: 8 },
  summaryLabel: { fontSize: 13, color: colors.gray600 },
  summaryVal: { fontSize: 13, fontWeight: '600', color: colors.gray800 },
  sep: { height: 1, backgroundColor: colors.secondary300, marginVertical: 10 },
  totalLabel: { fontSize: 16, fontWeight: '700', color: colors.gray800 },
  totalVal: { fontSize: 20, fontWeight: '700', color: colors.primary },
  orderBtn: {
    backgroundColor: colors.primary, paddingVertical: 15,
    borderRadius: radius.md, alignItems: 'center', ...shadow.craftLg,
  },
  orderBtnDisabled: { opacity: 0.6 },
  orderBtnText: { color: colors.white, fontWeight: '700', fontSize: 16 },
  secureNote: { textAlign: 'center', fontSize: 12, color: colors.gray600, marginTop: spacing.sm },
});
