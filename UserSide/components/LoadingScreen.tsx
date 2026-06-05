import React, { useEffect, useRef } from 'react';
import { View, Text, StyleSheet, Animated, Easing } from 'react-native';

interface LoadingScreenProps {
  visible: boolean;
  statusText?: string;
}

const LoadingScreen: React.FC<LoadingScreenProps> = ({ visible, statusText }) => {
  const letterAnim = useRef(new Animated.Value(0)).current;
  const translateXAnim = useRef(new Animated.Value(-100)).current;
  const rotateAnim = useRef(new Animated.Value(0)).current;
  const scaleAnim = useRef(new Animated.Value(0.5)).current;
  const opacityAnim = useRef(new Animated.Value(0)).current;

  // Individual letter animations
  const letterAnims = useRef(
    Array.from({ length: 9 }, () => ({
      opacity: new Animated.Value(0),
      translateY: new Animated.Value(-30),
      scale: new Animated.Value(0.5),
    }))
  ).current;

  const startAnimation = () => {
    // Reset all animations
    letterAnim.setValue(0);
    translateXAnim.setValue(-100);
    rotateAnim.setValue(0);
    scaleAnim.setValue(0.5);
    opacityAnim.setValue(0);

    letterAnims.forEach(anim => {
      anim.opacity.setValue(0);
      anim.translateY.setValue(-30);
      anim.scale.setValue(0.5);
    });

    // Start "A" letter loop animation
    const loopA = Animated.sequence([
      Animated.timing(letterAnim, {
        toValue: 1,
        duration: 1000, // Reduced from 1500ms
        easing: Easing.bezier(0.4, 0.0, 0.2, 1),
        useNativeDriver: true,
      }),
    ]);

    // Parallel animations for the "A" letter
    const aLetterAnimations = Animated.parallel([
      Animated.timing(translateXAnim, {
        toValue: 0,
        duration: 1000, // Reduced from 1500ms
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(rotateAnim, {
        toValue: 360,
        duration: 1000, // Reduced from 1500ms
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(scaleAnim, {
        toValue: 1,
        duration: 1000, // Reduced from 1500ms
        easing: Easing.elastic(1.2),
        useNativeDriver: true,
      }),
      Animated.timing(opacityAnim, {
        toValue: 1,
        duration: 500, // Reduced from 800ms
        easing: Easing.out(Easing.quad),
        useNativeDriver: true,
      }),
    ]);

    // Rest of letters animation (9 letters: lertDavao)
    // Start at 1000ms, 150ms stagger = 1000 + (9-1)*150 + 300 = 2500ms total
    const restLettersAnimation = letterAnims.map((anim, index) =>
      Animated.parallel([
        Animated.timing(anim.opacity, {
          toValue: 1,
          duration: 300, // Reduced from 400ms
          delay: 1000 + index * 150, // Start after A letter, stagger by 150ms
          easing: Easing.out(Easing.quad),
          useNativeDriver: true,
        }),
        Animated.timing(anim.translateY, {
          toValue: 0,
          duration: 300, // Reduced from 400ms
          delay: 1000 + index * 150,
          easing: Easing.elastic(1.1),
          useNativeDriver: true,
        }),
        Animated.timing(anim.scale, {
          toValue: 1,
          duration: 300, // Reduced from 400ms
          delay: 1000 + index * 150,
          easing: Easing.elastic(1.1),
          useNativeDriver: true,
        }),
      ])
    );

    // Run all animations
    Animated.parallel([
      aLetterAnimations,
      ...restLettersAnimation,
    ]).start();
  };

  useEffect(() => {
    if (visible) {
      startAnimation();

      // Don't loop on initial load - let it play once and hold
      // The app will transition after animation completes
    }
  }, [visible]);

  if (!visible) return null;

  const rotateInterpolate = rotateAnim.interpolate({
    inputRange: [0, 360],
    outputRange: ['0deg', '360deg'],
  });

  const restLetters = ['l', 'e', 'r', 't', 'D', 'a', 'v', 'a', 'o'];

  return (
    <View style={styles.container}>
      <View style={styles.logoContainer}>
        <Animated.Text
          style={[
            styles.letterA,
            {
              opacity: opacityAnim,
              transform: [
                { translateX: translateXAnim },
                { rotate: rotateInterpolate },
                { scale: scaleAnim },
              ],
            },
          ]}
        >
          A
        </Animated.Text>

        <View style={styles.restText}>
          {restLetters.map((letter, index) => (
            <Animated.Text
              key={index}
              style={[
                styles.letter,
                {
                  opacity: letterAnims[index].opacity,
                  transform: [
                    { translateY: letterAnims[index].translateY },
                    { scale: letterAnims[index].scale },
                  ],
                },
              ]}
            >
              {letter}
            </Animated.Text>
          ))}
        </View>
      </View>

      {/* Loading text with pulsing animation */}
      <Text style={styles.loadingText}>{statusText || "Preparing your experience..."}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: '#f8f9fa',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1000,
  },
  logoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    height: 120,
    marginBottom: 30,
  },
  letterA: {
    fontFamily: 'System',
    fontSize: 72,
    fontWeight: 'bold',
    color: '#1D3557',
    marginRight: -2,
  },
  restText: {
    flexDirection: 'row',
  },
  letter: {
    fontFamily: 'System',
    fontSize: 72,
    fontWeight: 'bold',
    color: '#1D3557',
  },
  loadingText: {
    fontSize: 16,
    color: '#666',
    marginTop: 20,
    fontWeight: '500',
  },
});

export default LoadingScreen;