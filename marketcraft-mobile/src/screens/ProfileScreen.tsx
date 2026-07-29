import React, { useEffect, useState } from 'react';
import {
  View, Text, ScrollView, StyleSheet, TouchableOpacity,
  ActivityIndicator, Alert, RefreshControl,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useAuth } from '../contexts/AuthContext';
import { ordersAPI } from '../services/api';
import { colors, spacing, radius, shadow } from '../theme';

const STATUS: Record<string, { label: string; bg: string; fg: string }> = {
  en_attente:     { label: 'En attente',     bg: '#fef9c3', fg: '#92400e' },
  confirmee:      { label: 'Confirmée',      bg: '#dbeafe', fg: '#1e40af' },
  en_preparation: { label: 'En préparation', bg: '#e0e7ff', fg: '#3730a3' },
  expediee:       { label: 'Expédiée',       bg: '#f3e8ff', fg: '#6b21a8' },
  livree:         { label: 'Livrée',         bg: '#dcfce7', fg: '#166534' },
  annulee:        { label: 'Annulée',        bg: '#fee2e2', fg: '#991b1b' },
};

const ROLE_LABEL: Record<string, string> = {
  client: 'Acheteur', acheteur: 'Acheteur', vendeur: 'Artisan vendeur', admin: 'Administrateur',
};

function StatusBadge({ statut }: { statut: string }) {
  const s = STATUS[statut] || { label: statut, bg: '#f3f4f6', fg: '#374151' };
  return (
    <View style={[styles.badge, { backgroundColor: s.bg }]}>
      <Text style={[styles.badgeText, { color: s.fg }]}>{s.label}</Text>
    </View>
  );
}

function InfoRow({ label, value }: { label: string; value?: string }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value || '—'}</Text>
    </View>
  );
}

export default function ProfileScreen() {
  const { user, isVendeur, logout } = useAuth();
  const navigation = useNavigation<any>();
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const res = await ordersAPI.getAll();
      const d = res.data;
      setOrders(Array.isArray(d) ? d : d?.data ?? []);
    } catch {
      setOrders([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { load(); }, []);
  const onRefresh = () => { setRefreshing(true); load(true); };

  const confirmLogout = () => {
    Alert.alert('Déconnexion', 'Voulez-vous vous déconnecter ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Se déconnecter', style: 'destructive', onPress: logout },
    ]);
  };

  const initials = ((user?.prenom || '')[0] || '') + ((user?.nom || '')[0] || '') || '👤';

  return (
    <ScrollView
      style={styles.container}
      showsVerticalScrollIndicator={false}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
    >
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.avatar}><Text style={styles.avatarText}>{initials.toUpperCase()}</Text></View>
        <Text style={styles.name}>{user?.prenom} {user?.nom}</Text>
        <Text style={styles.email}>{user?.email}</Text>
        <View style={styles.roleBadge}><Text style={styles.roleText}>{ROLE_LABEL[user?.role || ''] || user?.role}</Text></View>
      </View>

      <View style={{ padding: spacing.md }}>
        {/* Mes informations */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Mes informations</Text>
          <InfoRow label="Prénom" value={user?.prenom} />
          <InfoRow label="Nom" value={user?.nom} />
          <InfoRow label="E-mail" value={user?.email} />
          <InfoRow label="Rôle" value={ROLE_LABEL[user?.role || ''] || user?.role} />
        </View>

        {/* Raccourci statistiques (acheteur) */}
        {!isVendeur && (
          <TouchableOpacity style={styles.linkCard} onPress={() => navigation.navigate('MesStats')}>
            <Text style={styles.linkText}>📊  Mes statistiques d'achat</Text>
            <Text style={styles.chevron}>›</Text>
          </TouchableOpacity>
        )}

        {/* Mes commandes */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Mes commandes</Text>
          {loading ? (
            <ActivityIndicator color={colors.primary} style={{ paddingVertical: spacing.md }} />
          ) : orders.length === 0 ? (
            <Text style={styles.empty}>Aucune commande pour l'instant.</Text>
          ) : orders.map((o) => (
            <View key={o.id} style={styles.orderRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.orderId}>#{o.id}</Text>
                <Text style={styles.orderDate}>{new Date(o.created_at).toLocaleDateString('fr-FR')}</Text>
              </View>
              <View style={{ alignItems: 'flex-end', gap: 4 }}>
                <Text style={styles.orderAmount}>{Number(o.montant_total ?? o.total ?? 0).toFixed(2)} €</Text>
                <StatusBadge statut={o.statut} />
              </View>
            </View>
          ))}
        </View>

        {/* Déconnexion */}
        <TouchableOpacity style={styles.logoutBtn} onPress={confirmLogout}>
          <Text style={styles.logoutText}>Se déconnecter</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.secondary },

  header: { backgroundColor: colors.primary, alignItems: 'center', paddingVertical: spacing.lg },
  avatar: { width: 68, height: 68, borderRadius: 34, backgroundColor: 'rgba(255,255,255,0.2)', alignItems: 'center', justifyContent: 'center', marginBottom: spacing.sm },
  avatarText: { color: colors.white, fontSize: 24, fontWeight: '700' },
  name: { color: colors.white, fontSize: 18, fontWeight: '700' },
  email: { color: 'rgba(255,255,255,0.8)', fontSize: 13, marginTop: 2 },
  roleBadge: { marginTop: 8, backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 12, paddingVertical: 4, borderRadius: radius.full },
  roleText: { color: colors.white, fontSize: 12, fontWeight: '600' },

  card: {
    backgroundColor: colors.white, borderRadius: radius.md, padding: spacing.md,
    marginBottom: spacing.sm, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craft,
  },
  cardTitle: { fontSize: 15, fontWeight: '700', color: colors.gray800, marginBottom: spacing.sm },

  infoRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.secondary200 },
  infoLabel: { fontSize: 13, color: colors.gray600 },
  infoValue: { fontSize: 13, color: colors.gray800, fontWeight: '600' },

  linkCard: {
    backgroundColor: colors.white, borderRadius: radius.md, padding: spacing.md,
    marginBottom: spacing.sm, borderWidth: 1, borderColor: colors.secondary300,
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', ...shadow.craft,
  },
  linkText: { fontSize: 14, color: colors.gray800, fontWeight: '600' },
  chevron: { fontSize: 22, color: colors.gray400 },

  empty: { color: colors.gray400, fontSize: 13, textAlign: 'center', paddingVertical: spacing.md },
  orderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.secondary200 },
  orderId: { fontSize: 13, fontWeight: '700', color: colors.gray800 },
  orderDate: { fontSize: 11, color: colors.gray400, marginTop: 2 },
  orderAmount: { fontSize: 14, fontWeight: '700', color: colors.primary },

  badge: { paddingHorizontal: 7, paddingVertical: 3, borderRadius: radius.full },
  badgeText: { fontSize: 10, fontWeight: '700' },

  logoutBtn: { marginTop: spacing.sm, backgroundColor: colors.redLight, borderRadius: radius.md, paddingVertical: 13, alignItems: 'center' },
  logoutText: { color: colors.red, fontSize: 15, fontWeight: '700' },
});
