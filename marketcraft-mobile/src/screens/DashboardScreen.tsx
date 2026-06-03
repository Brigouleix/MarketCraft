import React, { useState } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity, StyleSheet,
  FlatList, Image, TextInput, Alert,
} from 'react-native';
import { useAuth } from '../contexts/AuthContext';
import { colors, spacing, radius, shadow } from '../theme';

const MOCK_ORDERS = [
  { id: 1042, client: 'Sophie M.', montant: 90,  statut: 'pending',   date: '04/06/2026', produit: 'Vase céramique' },
  { id: 1041, client: 'Paul D.',   montant: 45,  statut: 'shipped',   date: '02/06/2026', produit: 'Panier osier' },
  { id: 1038, client: 'Laura B.',  montant: 135, statut: 'delivered', date: '28/05/2026', produit: 'Collier argent' },
];

const MOCK_PRODUCTS = [
  { id: 1, nom: 'Vase céramique', prix: 45, stock: 8,  image: 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=200&q=60' },
  { id: 2, nom: 'Bol en grès',    prix: 32, stock: 0,  image: 'https://images.unsplash.com/photo-1481349518771-20055b2a7b24?w=200&q=60' },
];

const STATUS_MAP: Record<string, { label: string; bg: string; fg: string }> = {
  pending:   { label: 'En attente', bg: '#fef9c3', fg: '#92400e' },
  shipped:   { label: 'Expédié',    bg: '#dbeafe', fg: '#1e40af' },
  delivered: { label: 'Livré',      bg: '#dcfce7', fg: '#166534' },
};

type Tab = 'overview' | 'orders' | 'products';

export default function DashboardScreen() {
  const { user, logout } = useAuth();
  const [tab, setTab] = useState<Tab>('overview');

  const tabs: { key: Tab; label: string; emoji: string }[] = [
    { key: 'overview', label: 'Vue d\'ensemble', emoji: '📊' },
    { key: 'orders',   label: 'Commandes',       emoji: '📦' },
    { key: 'products', label: 'Produits',        emoji: '🏺' },
  ];

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Bonjour, {user?.prenom} 👋</Text>
          <Text style={styles.shopName}>Céramiques de Lyon · Boutique active</Text>
        </View>
        <TouchableOpacity style={styles.logoutBtn} onPress={() => Alert.alert('Déconnexion', 'Voulez-vous vous déconnecter ?', [
          { text: 'Annuler', style: 'cancel' },
          { text: 'Déconnecter', style: 'destructive', onPress: logout },
        ])}>
          <Text style={styles.logoutText}>Quitter</Text>
        </TouchableOpacity>
      </View>

      {/* Tab bar */}
      <View style={styles.tabBar}>
        {tabs.map(({ key, label, emoji }) => (
          <TouchableOpacity
            key={key}
            style={[styles.tabBtn, tab === key && styles.tabBtnActive]}
            onPress={() => setTab(key)}
          >
            <Text style={styles.tabEmoji}>{emoji}</Text>
            <Text style={[styles.tabLabel, tab === key && styles.tabLabelActive]}>{label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        {tab === 'overview' && <OverviewTab />}
        {tab === 'orders'   && <OrdersTab />}
        {tab === 'products' && <ProductsTab />}
      </ScrollView>
    </View>
  );
}

function OverviewTab() {
  const kpis = [
    { label: 'Chiffre d\'affaires', val: '3 240 €', trend: '↑ +12% ce mois', color: colors.primary },
    { label: 'Commandes',           val: '47',       trend: '↑ +5 cette semaine', color: colors.accent },
    { label: 'Note moyenne',        val: '4.8 ★',   trend: 'Sur 24 avis',    color: colors.amber },
    { label: 'Produits actifs',     val: '24',       trend: '3 en rupture',   color: colors.blue },
  ];
  return (
    <View style={{ padding: spacing.md }}>
      <View style={styles.kpiGrid}>
        {kpis.map((k) => (
          <View key={k.label} style={styles.kpiCard}>
            <Text style={styles.kpiLabel}>{k.label}</Text>
            <Text style={[styles.kpiVal, { color: k.color }]}>{k.val}</Text>
            <Text style={styles.kpiTrend}>{k.trend}</Text>
          </View>
        ))}
      </View>
      <Text style={styles.sectionTitle}>Dernières commandes</Text>
      {MOCK_ORDERS.map((o) => <OrderRow key={o.id} order={o} />)}
    </View>
  );
}

function OrdersTab() {
  return (
    <View style={{ padding: spacing.md }}>
      <Text style={styles.sectionTitle}>Toutes les commandes</Text>
      {MOCK_ORDERS.map((o) => <OrderRow key={o.id} order={o} detailed />)}
    </View>
  );
}

function ProductsTab() {
  return (
    <View style={{ padding: spacing.md }}>
      <TouchableOpacity style={styles.addProductBtn}>
        <Text style={styles.addProductBtnText}>+ Ajouter un produit</Text>
      </TouchableOpacity>
      {MOCK_PRODUCTS.map((p) => (
        <View key={p.id} style={styles.productRow}>
          <Image source={{ uri: p.image }} style={styles.productThumb} resizeMode="cover" />
          <View style={{ flex: 1 }}>
            <Text style={styles.productName}>{p.nom}</Text>
            <Text style={styles.productPrice}>{p.prix} €</Text>
            <Text style={[styles.stockBadge, p.stock === 0 && styles.stockOut]}>
              {p.stock === 0 ? '✗ Rupture de stock' : `✓ ${p.stock} en stock`}
            </Text>
          </View>
          <View style={styles.productActions}>
            <TouchableOpacity style={styles.editBtn}><Text style={styles.editBtnText}>✏️</Text></TouchableOpacity>
            <TouchableOpacity style={styles.deleteBtn}><Text style={styles.deleteBtnText}>🗑</Text></TouchableOpacity>
          </View>
        </View>
      ))}
    </View>
  );
}

function OrderRow({ order, detailed }: { order: typeof MOCK_ORDERS[0]; detailed?: boolean }) {
  const status = STATUS_MAP[order.statut];
  return (
    <View style={styles.orderCard}>
      <View style={styles.orderHeader}>
        <Text style={styles.orderId}>#{order.id}</Text>
        <View style={[styles.statusBadge, { backgroundColor: status.bg }]}>
          <Text style={[styles.statusText, { color: status.fg }]}>{status.label}</Text>
        </View>
      </View>
      <Text style={styles.orderClient}>{order.client}</Text>
      {detailed && <Text style={styles.orderProduct}>{order.produit}</Text>}
      <View style={styles.orderFooter}>
        <Text style={styles.orderDate}>{order.date}</Text>
        <Text style={styles.orderAmount}>{order.montant} €</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },
  header: {
    backgroundColor: colors.primary, paddingHorizontal: spacing.md, paddingVertical: spacing.md,
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
  },
  greeting: { fontSize: 18, fontWeight: '700', color: colors.white },
  shopName: { fontSize: 12, color: 'rgba(255,255,255,0.75)', marginTop: 2 },
  logoutBtn: { backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: radius.sm },
  logoutText: { color: colors.white, fontSize: 12, fontWeight: '600' },

  tabBar: {
    flexDirection: 'row', backgroundColor: colors.white,
    borderBottomWidth: 1, borderBottomColor: colors.secondary300,
  },
  tabBtn: { flex: 1, alignItems: 'center', paddingVertical: 10, gap: 2 },
  tabBtnActive: { borderBottomWidth: 2, borderBottomColor: colors.primary },
  tabEmoji: { fontSize: 16 },
  tabLabel: { fontSize: 11, color: colors.gray600, fontWeight: '500' },
  tabLabelActive: { color: colors.primary, fontWeight: '700' },

  content: { flex: 1 },

  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginBottom: spacing.lg },
  kpiCard: {
    width: '47%', backgroundColor: colors.white, borderRadius: radius.md,
    padding: spacing.md, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
  },
  kpiLabel: { fontSize: 11, color: colors.gray600, fontWeight: '500', marginBottom: 6 },
  kpiVal: { fontSize: 24, fontWeight: '700' },
  kpiTrend: { fontSize: 11, color: colors.accent, marginTop: 4 },

  sectionTitle: { fontSize: 17, fontWeight: '700', color: colors.gray800, marginBottom: spacing.sm },

  orderCard: {
    backgroundColor: colors.white, borderRadius: radius.md, padding: spacing.md,
    marginBottom: spacing.sm, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
  },
  orderHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  orderId: { fontSize: 13, fontWeight: '700', color: colors.gray800 },
  statusBadge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.full },
  statusText: { fontSize: 11, fontWeight: '700' },
  orderClient: { fontSize: 14, fontWeight: '600', color: colors.gray800, marginBottom: 2 },
  orderProduct: { fontSize: 12, color: colors.gray600, marginBottom: 6 },
  orderFooter: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 6 },
  orderDate: { fontSize: 11, color: colors.gray400 },
  orderAmount: { fontSize: 15, fontWeight: '700', color: colors.primary },

  addProductBtn: {
    backgroundColor: colors.primary, paddingVertical: 12, borderRadius: radius.md,
    alignItems: 'center', marginBottom: spacing.md, ...shadow.craft,
  },
  addProductBtnText: { color: colors.white, fontWeight: '700', fontSize: 14 },

  productRow: {
    flexDirection: 'row', gap: spacing.sm, backgroundColor: colors.white,
    borderRadius: radius.md, padding: spacing.sm, marginBottom: spacing.sm,
    borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
    alignItems: 'center',
  },
  productThumb: { width: 60, height: 60, borderRadius: radius.sm },
  productName: { fontSize: 14, fontWeight: '600', color: colors.gray800, marginBottom: 2 },
  productPrice: { fontSize: 14, fontWeight: '700', color: colors.primary, marginBottom: 4 },
  stockBadge: { fontSize: 11, color: colors.accent, fontWeight: '600' },
  stockOut: { color: colors.red },
  productActions: { gap: 6 },
  editBtn: { width: 32, height: 32, borderRadius: radius.sm, backgroundColor: colors.secondary, alignItems: 'center', justifyContent: 'center' },
  editBtnText: { fontSize: 14 },
  deleteBtn: { width: 32, height: 32, borderRadius: radius.sm, backgroundColor: colors.redLight, alignItems: 'center', justifyContent: 'center' },
  deleteBtnText: { fontSize: 14 },
});
