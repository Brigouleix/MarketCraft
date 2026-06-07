import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  Alert, ActivityIndicator, KeyboardAvoidingView, Platform, ScrollView,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { colors, spacing, radius, shadow } from '../theme';
import { useAuth } from '../contexts/AuthContext';

type Role = 'acheteur' | 'vendeur';

export default function RegisterScreen() {
  const navigation = useNavigation<any>();
  const { register } = useAuth();
  const [form, setForm] = useState({ prenom: '', nom: '', email: '', password: '' });
  const [role, setRole]       = useState<Role>('acheteur');
  const [loading, setLoading] = useState(false);

  const update = (k: string) => (v: string) => setForm((f) => ({ ...f, [k]: v }));

  const handleRegister = async () => {
    const { prenom, nom, email, password } = form;
    if (!prenom || !nom || !email || !password) {
      Alert.alert('Erreur', 'Merci de remplir tous les champs.'); return;
    }
    if (password.length < 6) {
      Alert.alert('Erreur', 'Le mot de passe doit faire au moins 6 caractères.'); return;
    }
    setLoading(true);
    try {
      await register({ prenom, nom, email, password, role });
      navigation.reset({ index: 0, routes: [{ name: 'Main' }] });
    } catch {
      Alert.alert('Inscription impossible', 'Cet email est peut-être déjà utilisé.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <View style={styles.logoWrap}>
          <View style={styles.logoIcon}><Text style={styles.logoEmoji}>🔨</Text></View>
          <Text style={styles.logoText}>MarketCraft</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.title}>Créer un compte</Text>
          <Text style={styles.sub}>Rejoignez la communauté artisanale</Text>

          <View style={styles.roleRow}>
            {(['acheteur', 'vendeur'] as Role[]).map((r) => (
              <TouchableOpacity
                key={r}
                style={[styles.roleBtn, role === r && styles.roleBtnActive]}
                onPress={() => setRole(r)}
              >
                <Text style={styles.roleIcon}>{r === 'acheteur' ? '🛍' : '🏺'}</Text>
                <Text style={[styles.roleLabel, role === r && styles.roleLabelActive]}>
                  {r === 'acheteur' ? 'Acheteur' : 'Vendeur'}
                </Text>
              </TouchableOpacity>
            ))}
          </View>

          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Text style={styles.label}>Prénom</Text>
              <TextInput style={styles.input} placeholder="Jean" placeholderTextColor={colors.gray400} value={form.prenom} onChangeText={update('prenom')} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.label}>Nom</Text>
              <TextInput style={styles.input} placeholder="Dupont" placeholderTextColor={colors.gray400} value={form.nom} onChangeText={update('nom')} />
            </View>
          </View>

          <Text style={styles.label}>Adresse e-mail</Text>
          <TextInput style={styles.input} placeholder="vous@exemple.fr" placeholderTextColor={colors.gray400} keyboardType="email-address" autoCapitalize="none" value={form.email} onChangeText={update('email')} />

          <Text style={styles.label}>Mot de passe</Text>
          <TextInput style={styles.input} placeholder="Min. 6 caractères" placeholderTextColor={colors.gray400} secureTextEntry value={form.password} onChangeText={update('password')} />

          <TouchableOpacity
            style={[styles.btn, loading && { opacity: 0.6 }]}
            onPress={handleRegister}
            disabled={loading}
          >
            {loading
              ? <ActivityIndicator color={colors.white} />
              : <Text style={styles.btnText}>Créer mon compte</Text>
            }
          </TouchableOpacity>

          <View style={styles.footer}>
            <Text style={styles.footerText}>Déjà un compte ?</Text>
            <TouchableOpacity onPress={() => navigation.navigate('Login')}>
              <Text style={styles.footerLink}> Se connecter</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, backgroundColor: colors.secondary, padding: spacing.lg },
  logoWrap: { alignItems: 'center', marginVertical: spacing.lg },
  logoIcon: {
    width: 56, height: 56, backgroundColor: colors.primary,
    borderRadius: radius.md, alignItems: 'center', justifyContent: 'center', marginBottom: spacing.sm,
    ...shadow.craftLg,
  },
  logoEmoji: { fontSize: 26 },
  logoText: { fontSize: 26, fontWeight: '700', color: colors.primary },
  card: {
    backgroundColor: colors.white, borderRadius: radius.xl,
    padding: spacing.lg, borderWidth: 1, borderColor: colors.secondary300, ...shadow.craftLg,
  },
  title: { fontSize: 24, fontWeight: '700', color: colors.primary, marginBottom: 4 },
  sub: { fontSize: 14, color: colors.gray600, marginBottom: spacing.md },
  roleRow: { flexDirection: 'row', gap: 12, marginBottom: spacing.md },
  roleBtn: {
    flex: 1, padding: 12, borderRadius: radius.sm,
    borderWidth: 1.5, borderColor: colors.secondary400,
    alignItems: 'center', flexDirection: 'row', justifyContent: 'center', gap: 8,
  },
  roleBtnActive: { borderColor: colors.primary, backgroundColor: colors.primaryLight },
  roleIcon: { fontSize: 18 },
  roleLabel: { fontSize: 14, fontWeight: '600', color: colors.gray600 },
  roleLabelActive: { color: colors.primary },
  row: { flexDirection: 'row', gap: 12 },
  label: { fontSize: 13, fontWeight: '600', color: colors.gray800, marginBottom: 6 },
  input: {
    borderWidth: 1.5, borderColor: colors.secondary400, borderRadius: radius.sm,
    paddingHorizontal: 14, paddingVertical: 11, fontSize: 14, color: colors.gray800,
    marginBottom: spacing.md,
  },
  btn: {
    backgroundColor: colors.primary, paddingVertical: 14,
    borderRadius: radius.md, alignItems: 'center', marginTop: spacing.sm, ...shadow.craft,
  },
  btnText: { color: colors.white, fontWeight: '700', fontSize: 16 },
  footer: { flexDirection: 'row', justifyContent: 'center', marginTop: spacing.md },
  footerText: { fontSize: 14, color: colors.gray600 },
  footerLink: { fontSize: 14, fontWeight: '700', color: colors.primary },
});
