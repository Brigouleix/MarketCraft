export const colors = {
  primary:        '#8B4513',
  primaryDark:    '#7a3c10',
  primaryLight:   '#fae8d8',
  secondary:      '#F5F0E8',
  secondary200:   '#f5f0e8',
  secondary300:   '#ede4d4',
  secondary400:   '#e0d2b8',
  accent:         '#6B7C3F',
  accentLight:    '#e5ebd5',
  amber:          '#fbbf24',
  white:          '#ffffff',
  gray400:        '#9ca3af',
  gray600:        '#4b5563',
  gray800:        '#1f2937',
  red:            '#dc2626',
  redLight:       '#fee2e2',
  blue:           '#1e40af',
  blueLight:      '#dbeafe',
  green:          '#166534',
  greenLight:     '#dcfce7',
  purple:         '#7c3aed',
  purpleLight:    '#f5f3ff',
};

export const typography = {
  serif:  { fontFamily: 'Georgia' } as const,
  sans:   { fontFamily: 'System'  } as const,
};

export const spacing = {
  xs: 4, sm: 8, md: 16, lg: 24, xl: 32, xxl: 48,
};

export const radius = {
  sm: 8, md: 12, lg: 16, xl: 20, full: 999,
};

export const shadow = {
  craft: {
    shadowColor: '#8B4513',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.10,
    shadowRadius: 4,
    elevation: 3,
  },
  craftLg: {
    shadowColor: '#8B4513',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 6,
  },
};
