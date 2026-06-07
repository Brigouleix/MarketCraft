import React, { useEffect, useState } from 'react';
import {
  View, Text, ScrollView, StyleSheet, ActivityIndicator,
  TouchableOpacity, RefreshControl,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { dashboardAPI } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { colors, spacing, radius, shadow } from '../theme';

const STATUS: Record<string, { label: string; bg: string; fg: string }> = {
  en_attente:     { label: 'En attente',     bg: '#fef9c3', fg: '#92400e' },
  confirmee:      { label: 'Confirmée',      bg: '#dbeafe', fg: '#1e40af' },
  en_preparation: { label: 'En préparation', bg: '#e0e7ff', fg: '#3730a3' },
  expediee:       { label: 'Expédiée',       bg: '#f3e8ff', fg: '#6b21a8' },
  livree:         { label: 'Livrée',         bg: '#dcfce7', fg: '#166534' },
  annulee:        { label: 'Annulée',        bg: '#fee2e2', fg: '#991b1b' },
};

function StatCard({ emoji, label, value, sub, color }: { emoji: string; label: string; value: string; sub?: string; color: string }) {
  return (
    <View style={[styles.kpiCard, { borderLeftWidth: 3, borderLeftColor: color }]}>
      <Text style={styles.kpiEmoji}>{emoji}</Text>
      <Text style={styles.kpiLabel}>{label}</Text>
      <Text style={[styles.kpiVal, { color }]}>{value}</Text>
      {sub && <Text style={styles.kpiSub}>{sub}</Text>}
    </View>
  );
}

function StatusBadge({ statut }: { statut: string }) {
  const s = STATUS[statut] || { label: statut, bg: '#f3f4f6', fg: '#374151' };
  return (
    <View style={[styles.badge, { backgroundColor: s.bg }]}>
      <Text style={[styles.badgeText, { color: s.fg }]}>{s.label}</Text>
    </View>
  );
}

function BarRow({ label, value, max, suffix = '€' }: { label: string; value: number; max: number; suffix?: string }) {
  const pct = max > 0 ? (value / max) : 0;
  return (
    <View style={styles.barRow}>
      <Text style={styles.barLabel} numberOfLines={1}>{label}</Text>
      <View style={styles.barTrack}>
        <View style={[styles.barFill, { width: `${Math.round(pct * 100)}%` }]} />
      </View>
      <Text style={styles.barValue}>{Number(value).toFixed(0)} {suffix}</Text>
    </View>
  );
}

export default function BuyerStatsScreen() {
  const { user } = useAuth();
  const navigation = useNavigation<any>();
  const [data, setData]         = useState<any>(null);
  const [loading, setLoading]   = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError]       = useState(false);

  const load = async (silent = false) => {
    if (!silent) setLoading(true);
    setError(false);
    try {
      const res = await dashboardAPI.getAcheteurStats();
      setData(res.data?.data ?? res.data);
    } catch {
      setError(true);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { load(); }, []);

  const onRefresh = () => { setRefreshing(true); load(true); };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (error || !data) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>Impossible de charger vos statistiques.</Text>
        <TouchableOpacity style={styles.retryBtn} onPress={() => load()}>
          <Text style={styles.retryBtnText}>Réessayer</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const maxDep = Math.max(...(data.depenses_mois?.map((d: any) => Number(d.total)) || [1]), 1);
  const maxCat = Math.max(...(data.categories_pref?.map((c: any) => Number(c.total_depense)) || [1]), 1);

  return (
    <ScrollView
      style={styles.container}
      showsVerticalScrollIndicator={false}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
    >
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Mes statistiques</Text>
        <Text style={styles.headerSub}>Bonjour, {user?.prenom} 👋</Text>
      </View>

      <View style={{ padding: spacing.md }}>
        {/* KPIs */}
        <View style={styles.kpiGrid}>
          <StatCard emoji="💸" label="Total dépensé"   value={`${Number(data.total_depense || 0).toFixed(2)} €`} color={colors.primary}   sub="Hors annulations" />
          <StatCard emoji="🛒" label="Commandes"        value={String(data.nb_commandes || 0)}                    color="#2563eb" />
          <StatCard emoji="📊" label="Panier moyen"     value={`${Number(data.panier_moyen || 0).toFixed(2)} €`}  color={colors.accent}    sub="Par commande" />
          <StatCard emoji="⭐" label="Avis déposés"     value={String(data.nb_avis || 0)}                         color="#d97706" />
        </View>

        {/* Dépenses par mois */}
        {data.depenses_mois?.length > 0 && (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Dépenses — 6 derniers mois</Text>
            <View style={{ gap: 12 }}>
              {data.depenses_mois.map((d: any) => (
                <BarRow
                  key={d.mois}
                  label={new Date(d.mois + '-01').toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' })}
                  value={Number(d.total)}
                  max={maxDep}
                />
              ))}
            </View>
          </View>
        )}

        {/* Catégories préférées */}
        {data.categories_pref?.length > 0 && (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Catégories préférées</Text>
            <View style={{ gap: 12 }}>
              {data.categories_pref.map((c: any) => (
                <BarRow
                  key={c.categorie}
                  label={c.categorie || 'Non catégorisé'}
                  value={Number(c.total_depense)}
                  max={maxCat}
                />
              ))}
            </View>
          </View>
        )}

        {/* Historique commandes */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Historique des commandes</Text>
          {data.commandes?.length > 0 ? data.commandes.map((o: any) => (
            <View key={o.id} style={styles.orderItem}>
              <View style={{ flex: 1 }}>
                <Text style={styles.orderId}>#{o.id}</Text>
                <Text style={styles.orderDate}>{new Date(o.created_at).toLocaleDateString('fr-FR')} · {o.nb_articles} article(s)</Text>
              </View>
              <View style={{ alignItems: 'flex-end', gap: 4 }}>
                <Text style={styles.orderAmount}>{Number(o.montant_total).toFixed(2)} €</Text>
                <StatusBadge statut={o.statut} />
              </View>
            </View>
          )) : (
            <View style={styles.emptyWrap}>
              <Text style={{ fontSize: 36, marginBottom: spacing.sm }}>🛒</Text>
              <Text style={styles.emptyText}>Aucune commande pour l'instant.</Text>
              <TouchableOpacity style={styles.ctaBtn} onPress={() => navigation.navigate('Catalogue')}>
                <Text style={styles.ctaBtnText}>Explorer les produits</Text>
              </TouchableOpacity>
            </View>
          )}
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },
  center:    { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.lg },
  errorText: { fontSize: 15, color: colors.gray600, marginBottom: spacing.md, textAlign: 'center' },
  retryBtn:  { backgroundColor: colors.primary, paddingHorizontal: 24, paddingVertical: 10, borderRadius: radius.md },
  retryBtnText:{ color: colors.white, fontWeight: '700' },

  header: { backgroundColor: colors.primary, padding: spacing.lg, paddingBottom: spacing.xl },
  headerTitle: { fontSize: 22, fontWeight: '700', color: colors.white, marginBottom: 4 },
  headerSub:   { fontSize: 14, color: 'rgba(255,255,255,0.75)' },

  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginBottom: spacing.md },
  kpiCard: {
    width: '47%', backgroundColor: colors.white, borderRadius: radius.md,
    padding: spacing.md, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
  },
  kpiEmoji: { fontSize: 20, marginBottom: 6 },
  kpiLabel: { fontSize: 11, color: colors.gray600, marginBottom: 4 },
  kpiVal:   { fontSize: 20, fontWeight: '700' },
  kpiSub:   { fontSize: 10, color: colors.gray400, marginTop: 2 },

  card: {
    backgroundColor: colors.white, borderRadius: radius.md, padding: spacing.md,
    marginBottom: spacing.sm, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
  },
  cardTitle: { fontSize: 15, fontWeight: '700', color: colors.gray800, marginBottom: spacing.md },

  barRow:  { flexDirection: 'row', alignItems: 'center', gap: 8 },
  barLabel:{ width: 60, fontSize: 11, color: colors.gray600 },
  barTrack:{ flex: 1, height: 8, backgroundColor: colors.secondary300, borderRadius: 4, overflow: 'hidden' },
  barFill: { height: 8, backgroundColor: colors.primary, borderRadius: 4 },
  barValue:{ width: 60, fontSize: 11, fontWeight: '700', color: colors.primary, textAlign: 'right' },

  orderItem:{
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.secondary300,
  },
  orderId:     { fontSize: 13, fontWeight: '700', color: colors.gray800 },
  orderDate:   { fontSize: 11, color: colors.gray400, marginTop: 2 },
  orderAmount: { fontSize: 14, fontWeight: '700', color: colors.primary },

  badge:    { paddingHorizontal: 7, paddingVertical: 3, borderRadius: radius.full },
  badgeText:{ fontSize: 10, fontWeight: '700' },

  emptyWrap:{ alignItems: 'center', paddingVertical: spacing.lg },
  emptyText:{ fontSize: 14, color: colors.gray600, marginBottom: spacing.md },
  ctaBtn:   { backgroundColor: colors.primary, paddingHorizontal: 24, paddingVertical: 10, borderRadius: radius.md },
  ctaBtnText:{ color: colors.white, fontWeight: '700' },
});
