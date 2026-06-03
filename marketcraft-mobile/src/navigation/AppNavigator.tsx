import React from 'react';
import { View, Text, ActivityIndicator } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';

import { useAuth } from '../contexts/AuthContext';
import { useCart } from '../contexts/CartContext';
import { colors } from '../theme';

import HomeScreen         from '../screens/HomeScreen';
import CatalogScreen      from '../screens/CatalogScreen';
import ProductDetailScreen from '../screens/ProductDetailScreen';
import CartScreen         from '../screens/CartScreen';
import CheckoutScreen     from '../screens/CheckoutScreen';
import LoginScreen        from '../screens/LoginScreen';
import RegisterScreen     from '../screens/RegisterScreen';
import DashboardScreen    from '../screens/DashboardScreen';

const Stack = createNativeStackNavigator();
const Tab   = createBottomTabNavigator();

function MainTabs() {
  const { isAuthenticated, isVendeur } = useAuth();
  const { count } = useCart();

  return (
    <Tab.Navigator
      screenOptions={{
        tabBarActiveTintColor:   colors.primary,
        tabBarInactiveTintColor: colors.gray600,
        tabBarStyle: { borderTopColor: colors.secondary300 },
        headerStyle: { backgroundColor: colors.white },
        headerTintColor: colors.primary,
        headerTitleStyle: { fontWeight: '700' },
      }}
    >
      <Tab.Screen
        name="Home"
        component={HomeScreen}
        options={{
          title: 'Accueil',
          tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>🏠</Text>,
          headerTitle: '🔨 MarketCraft',
        }}
      />
      <Tab.Screen
        name="Catalogue"
        component={CatalogScreen}
        options={{
          title: 'Produits',
          tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>🏺</Text>,
        }}
      />
      <Tab.Screen
        name="Cart"
        component={CartScreen}
        options={{
          title: 'Panier',
          tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>🛒</Text>,
          tabBarBadge: count > 0 ? count : undefined,
          tabBarBadgeStyle: { backgroundColor: colors.primary },
        }}
      />
      {isVendeur && (
        <Tab.Screen
          name="Dashboard"
          component={DashboardScreen}
          options={{
            title: 'Dashboard',
            tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>📊</Text>,
            headerShown: false,
          }}
        />
      )}
      {!isAuthenticated && (
        <Tab.Screen
          name="Login"
          component={LoginScreen}
          options={{
            title: 'Connexion',
            tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>👤</Text>,
            headerShown: false,
          }}
        />
      )}
    </Tab.Navigator>
  );
}

export default function AppNavigator() {
  const { isLoading } = useAuth();

  if (isLoading) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.secondary }}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <Stack.Navigator
        screenOptions={{
          headerStyle: { backgroundColor: colors.white },
          headerTintColor: colors.primary,
          headerTitleStyle: { fontWeight: '700' },
        }}
      >
        <Stack.Screen name="Main" component={MainTabs} options={{ headerShown: false }} />
        <Stack.Screen name="ProductDetail" component={ProductDetailScreen} options={{ title: 'Produit' }} />
        <Stack.Screen name="Checkout"      component={CheckoutScreen}      options={{ title: 'Commander' }} />
        <Stack.Screen name="Register"      component={RegisterScreen}      options={{ headerShown: false }} />
      </Stack.Navigator>
    </NavigationContainer>
  );
}
