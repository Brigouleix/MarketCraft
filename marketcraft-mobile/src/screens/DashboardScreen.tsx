import React, { useState, useEffect } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity, StyleSheet,
  Image, Alert, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useAuth } from '../contexts/AuthContext';
import { dashboardAPI, productsAPI, ordersAPI, boutiquesAPI } from '../services/api';
import { colors, spacing, radius, shadow } from '../theme';

const STATUS: Record<string, { label: string; bg: string; fg: string }> = {
  en_attente:     { label: 'En attente',     bg: '#fef9c3', fg: '#92400e' },
  confirmee:      { label: 'Confirmée',      bg: '#dbeafe', fg: '#1e40af' },
  en_preparation: { label: 'En préparation', bg: '#e0e7ff', fg: '#3730a3' },
  expediee:       { label: 'Expédiée',       bg: '#f3e8ff', fg: '#6b21a8' },
  livree:         { label: 'Livrée',         bg: '#dcfce7', fg: '#166534' },
  annulee:        { label: 'Annulée',        bg: '#fee2e2', fg: '#991b1b' },
};

type Tab = 'overview' | 'orders' | 'products';

function StatusBadge({ statut }: { statut: string }) {
  const s = STATUS[statut] || { label: statut, bg: '#f3f4f6', fg: '#374151' };
  return (
    <View style={[styles.badge, { backgroundColor: s.bg }]}>
      <Text style={[styles.badgeText, { color: s.fg }]}>{s.label}</Text>
    </View>
  );
}

export default function DashboardScreen() {
  const { user, logout } = useAuth();
  const navigation = useNavigation<any>();
  const [tab, setTab] = useState<Tab>('overview');
  const [stats, setStats]       = useState<any>(null);
  const [orders, setOrders]     = useState<any[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading]   = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const [statsRes, ordersRes, productsRes] = await Promise.all([
        dashboardAPI.getVendeurStats(),
        ordersAPI.getAll(),
        productsAPI.getAll({ my: true }),
      ]);
      setStats(statsRes.data?.data ?? statsRes.data);
      const od = ordersRes.data;
      setOrders(Array.isArray(od) ? od : od?.data ?? od?.orders ?? []);
      const pd = productsRes.data;
      setProducts(Array.isArray(pd) ? pd : pd?.data ?? pd?.products ?? []);
    } catch (e) {
      // Silently fail — données vides affichées
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { load(); }, []);

  const onRefresh = () => { setRefreshing(true); load(true); };

  const updateStatus = async (orderId: number, newStatus: string) => {
    try {
      await ordersAPI.updateStatus(orderId, newStatus);
      setOrders((prev) => prev.map((o) => o.id === orderId ? { ...o, statut: newStatus } : o));
    } catch {
      Alert.alert('Erreur', 'Impossible de mettre à jour le statut.');
    }
  };

  const deleteProduct = (id: number) => {
    Alert.alert('Supprimer', 'Supprimer ce produit ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Supprimer', style: 'destructive', onPress: async () => {
        try {
          await productsAPI.delete(id);
          setProducts((prev) => prev.filter((p) => p.id !== id));
        } catch {
          Alert.alert('Erreur', 'Impossible de supprimer ce produit.');
        }
      }},
    ]);
  };

  const TABS: { key: Tab; label: string; emoji: string }[] = [
    { key: 'overview', label: "Vue d'ensemble", emoji: '📊' },
    { key: 'orders',   label: 'Commandes',       emoji: '📦' },
    { key: 'products', label: 'Produits',         emoji: '🏺' },
  ];

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Bonjour, {user?.prenom} 👋</Text>
          <Text style={styles.shopLabel}>Dashboard vendeur</Text>
        </View>
        <TouchableOpacity style={styles.logoutBtn} onPress={() =>
          Alert.alert('Déconnexion', '', [
            { text: 'Annuler', style: 'cancel' },
            { text: 'Déconnecter', style: 'destructive', onPress: logout },
          ])}>
          <Text style={styles.logoutText}>Quitter</Text>
        </TouchableOpacity>
      </View>

      {/* Tabs */}
      <View style={styles.tabBar}>
        {TABS.map(({ key, label, emoji }) => (
          <TouchableOpacity key={key} style={[styles.tabBtn, tab === key && styles.tabBtnActive]} onPress={() => setTab(key)}>
            <Text style={styles.tabEmoji}>{emoji}</Text>
            <Text style={[styles.tabLabel, tab === key && styles.tabLabelActive]}>{label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      {loading ? (
        <View style={styles.loadingWrap}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <ScrollView
          style={styles.content}
          showsVerticalScrollIndicator={false}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
        >
          {tab === 'overview' && <OverviewTab stats={stats} orders={orders} />}
          {tab === 'orders'   && <OrdersTab orders={orders} onUpdateStatus={updateStatus} />}
          {tab === 'products' && <ProductsTab products={products} onDelete={deleteProduct} navigation={navigation} />}
        </ScrollView>
      )}
    </View>
  );
}

// ── Overview Tab ─────────────────────────────────────────────────────────────

function OverviewTab({ stats, orders }: { stats: any; orders: any[] }) {
  const kpis = [
    { label: "Chiffre d'affaires", val: `${Number(stats?.ca || 0).toFixed(0)} €`, color: colors.accent },
    { label: 'Commandes',           val: String(stats?.nb_commandes || 0),         color: colors.primary },
    { label: 'Note moyenne',        val: `${Number(stats?.note_moyenne || 0).toFixed(1)} ★`, color: '#d97706' },
    { label: 'Produits actifs',     val: String(stats?.nb_produits || 0),           color: '#2563eb' },
  ];

  return (
    <View style={{ padding: spacing.md }}>
      {/* KPI grid */}
      <View style={styles.kpiGrid}>
        {kpis.map((k) => (
          <View key={k.label} style={styles.kpiCard}>
            <Text style={styles.kpiLabel}>{k.label}</Text>
            <Text style={[styles.kpiVal, { color: k.color }]}>{k.val}</Text>
          </View>
        ))}
      </View>

      {/* Top produits */}
      {stats?.top_produits?.length > 0 && (
        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Top produits</Text>
          {stats.top_produits.map((p: any, i: number) => (
            <View key={p.id} style={styles.topProdRow}>
              <Text style={styles.topProdRank}>#{i + 1}</Text>
              <View style={{ flex: 1 }}>
                <Text style={styles.topProdName} numberOfLines={1}>{p.nom}</Text>
                <Text style={styles.topProdSub}>{p.nb_vendus} vendu(s)</Text>
              </View>
              <Text style={styles.topProdRevenu}>{Number(p.revenu).toFixed(0)} €</Text>
            </View>
          ))}
        </View>
      )}

      {/* Commandes récentes */}
      <View style={styles.sectionCard}>
        <Text style={styles.sectionTitle}>Commandes récentes</Text>
        {(stats?.commandes_recentes?.length > 0) ? stats.commandes_recentes.slice(0, 5).map((o: any) => (
          <View key={o.id} style={styles.orderRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.orderId}>#{o.id} — {o.client_prenom} {o.client_nom}</Text>
              <Text style={styles.orderDate}>{new Date(o.created_at).toLocaleDateString('fr-FR')}</Text>
            </View>
            <View style={{ alignItems: 'flex-end', gap: 4 }}>
              <Text style={styles.orderAmount}>{Number(o.montant_total).toFixed(0)} €</Text>
              <StatusBadge statut={o.statut} />
            </View>
          </View>
        )) : (
          <Text style={styles.empty}>Aucune commande reçue.</Text>
        )}
      </View>
    </View>
  );
}

// ── Orders Tab ───────────────────────────────────────────────────────────────

const NEXT_STATUS: Record<string, string | null> = {
  en_attente:     'confirmee',
  confirmee:      'en_preparation',
  en_preparation: 'expediee',
  expediee:       'livree',
  livree:         null,
  annulee:        null,
};

function OrdersTab({ orders, onUpdateStatus }: { orders: any[]; onUpdateStatus: (id: number, s: string) => void }) {
  return (
    <View style={{ padding: spacing.md }}>
      <Text style={[styles.sectionTitle, { marginBottom: spacing.md }]}>Toutes les commandes ({orders.length})</Text>
      {orders.length === 0 ? (
        <Text style={styles.empty}>Aucune commande pour l'instant.</Text>
      ) : orders.map((o) => {
        const next = NEXT_STATUS[o.statut];
        const nextLabel = next ? STATUS[next]?.label : null;
        return (
          <View key={o.id} style={styles.sectionCard}>
            <View style={styles.orderHeader}>
              <Text style={styles.orderId}>#{o.id}</Text>
              <StatusBadge statut={o.statut} />
            </View>
            <Text style={styles.orderClient}>
              {o.client_prenom ?? o.utilisateur_prenom ?? ''} {o.client_nom ?? o.utilisateur_nom ?? '–'}
            </Text>
            <View style={[styles.orderRow, { marginTop: 8 }]}>
              <Text style={styles.orderDate}>{new Date(o.created_at).toLocaleDateString('fr-FR')}</Text>
              <Text style={styles.orderAmount}>{Number(o.montant_total ?? o.total ?? 0).toFixed(2)} €</Text>
            </View>
            {nextLabel && (
              <TouchableOpacity style={styles.advanceBtn} onPress={() => onUpdateStatus(o.id, next!)}>
                <Text style={styles.advanceBtnText}>→ Passer à : {nextLabel}</Text>
              </TouchableOpacity>
            )}
          </View>
        );
      })}
    </View>
  );
}

// ── Products Tab ─────────────────────────────────────────────────────────────

function ProductsTab({ products, onDelete, navigation }: { products: any[]; onDelete: (id: number) => void; navigation: any }) {
  return (
    <View style={{ padding: spacing.md }}>
      <View style={styles.prodHeader}>
        <Text style={styles.sectionTitle}>Mes produits ({products.length})</Text>
      </View>
      {products.length === 0 ? (
        <Text style={styles.empty}>Aucun produit. Créez-en un depuis le web.</Text>
      ) : products.map((p) => {
        const imgs = typeof p.images === 'string' ? JSON.parse(p.images || '[]') : (p.images || []);
        return (
          <View key={p.id} style={styles.productRow}>
            {imgs[0] ? (
              <Image source={{ uri: imgs[0] }} style={styles.productThumb} resizeMode="cover" />
            ) : (
              <View style={[styles.productThumb, { backgroundColor: colors.secondary300, alignItems: 'center', justifyContent: 'center' }]}>
                <Text style={{ fontSize: 20 }}>🖼</Text>
              </View>
            )}
            <View style={{ flex: 1 }}>
              <Text style={styles.productName} numberOfLines={1}>{p.nom}</Text>
              <Text style={styles.productPrice}>{Number(p.prix).toFixed(2)} €</Text>
              <Text style={[styles.stockText, { color: p.stock > 0 ? colors.accent : colors.red }]}>
                {p.stock > 0 ? `✓ ${p.stock} en stock` : '✗ Épuisé'}
              </Text>
            </View>
            <TouchableOpacity onPress={() => onDelete(p.id)} style={styles.deleteBtn}>
              <Text style={{ fontSize: 16 }}>🗑</Text>
            </TouchableOpacity>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },
  header: {
    backgroundColor: colors.primary, paddingHorizontal: spacing.md,
    paddingVertical: spacing.md, flexDirection: 'row',
    justifyContent: 'space-between', alignItems: 'center',
  },
  greeting:  { fontSize: 18, fontWeight: '700', color: colors.white },
  shopLabel: { fontSize: 12, color: 'rgba(255,255,255,0.7)', marginTop: 2 },
  logoutBtn: { backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: radius.sm },
  logoutText:{ color: colors.white, fontSize: 12, fontWeight: '600' },
  tabBar:    { flexDirection: 'row', backgroundColor: colors.white, borderBottomWidth: 1, borderBottomColor: colors.secondary300 },
  tabBtn:    { flex: 1, alignItems: 'center', paddingVertical: 10, gap: 2 },
  tabBtnActive: { borderBottomWidth: 2, borderBottomColor: colors.primary },
  tabEmoji:  { fontSize: 16 },
  tabLabel:  { fontSize: 11, color: colors.gray600, fontWeight: '500' },
  tabLabelActive: { color: colors.primary, fontWeight: '700' },
  content:   { flex: 1 },
  loadingWrap:{ flex: 1, alignItems: 'center', justifyContent: 'center' },
  empty:     { color: colors.gray400, fontSize: 14, textAlign: 'center', paddingVertical: spacing.lg },

  kpiGrid:   { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginBottom: spacing.md },
  kpiCard:   {
    width: '47%', backgroundColor: colors.white, borderRadius: radius.md,
    padding: spacing.md, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
  },
  kpiLabel:  { fontSize: 11, color: colors.gray600, marginBottom: 6 },
  kpiVal:    { fontSize: 22, fontWeight: '700' },

  sectionCard: {
    backgroundColor: colors.white, borderRadius: radius.md, padding: spacing.md,
    marginBottom: spacing.sm, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
  },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.gray800, marginBottom: spacing.sm },

  topProdRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, paddingVertical: 6 },
  topProdRank:{ fontSize: 12, fontWeight: '700', color: colors.gray400, width: 24 },
  topProdName:{ fontSize: 13, fontWeight: '600', color: colors.gray800 },
  topProdSub: { fontSize: 11, color: colors.gray600 },
  topProdRevenu:{ fontSize: 14, fontWeight: '700', color: colors.primary },

  orderRow:   { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  orderHeader:{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  orderId:    { fontSize: 13, fontWeight: '700', color: colors.gray800 },
  orderClient:{ fontSize: 14, fontWeight: '600', color: colors.gray800, marginBottom: 4 },
  orderDate:  { fontSize: 11, color: colors.gray400 },
  orderAmount:{ fontSize: 15, fontWeight: '700', color: colors.primary },
  advanceBtn: {
    marginTop: spacing.sm, backgroundColor: colors.primaryLight,
    paddingVertical: 8, paddingHorizontal: 12, borderRadius: radius.sm,
    alignItems: 'center',
  },
  advanceBtnText: { fontSize: 13, fontWeight: '600', color: colors.primary },

  badge:     { paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.full },
  badgeText: { fontSize: 10, fontWeight: '700' },

  prodHeader:{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: spacing.md },
  productRow:{
    flexDirection: 'row', gap: spacing.sm, backgroundColor: colors.white,
    borderRadius: radius.md, padding: spacing.sm, marginBottom: spacing.sm,
    borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft, alignItems: 'center',
  },
  productThumb:{ width: 56, height: 56, borderRadius: radius.sm },
  productName: { fontSize: 13, fontWeight: '600', color: colors.gray800, marginBottom: 2 },
  productPrice:{ fontSize: 14, fontWeight: '700', color: colors.primary, marginBottom: 2 },
  stockText:   { fontSize: 11, fontWeight: '600' },
  deleteBtn:   { width: 32, height: 32, borderRadius: radius.sm, backgroundColor: colors.redLight, alignItems: 'center', justifyContent: 'center' },
});
