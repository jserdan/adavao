import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, Platform, Animated } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export default function NetworkBanner() {
    const [isConnected, setIsConnected] = useState<boolean | null>(true);
    const insets = useSafeAreaInsets();
    const [animation] = useState(new Animated.Value(0));

    useEffect(() => {
        const unsubscribe = NetInfo.addEventListener(state => {
            // NetInfo might initially report false before truly checking, so we only 
            // set false if it's explicitly disconnected and not just unknown.
            if (state.isConnected === false && state.isInternetReachable === false) {
                setIsConnected(false);
            } else if (state.isConnected === true) {
                setIsConnected(true);
            }
        });

        return () => unsubscribe();
    }, []);

    useEffect(() => {
        Animated.spring(animation, {
            toValue: isConnected === false ? 1 : 0,
            useNativeDriver: true,
            bounciness: 10,
            speed: 12
        }).start();
    }, [isConnected, animation]);

    if (isConnected !== false) {
        return null;
    }

    const translateY = animation.interpolate({
        inputRange: [0, 1],
        outputRange: [-100, 0] // Slide down
    });

    return (
        <Animated.View style={[
            styles.container,
            {
                paddingTop: Math.max(insets.top, Platform.OS === 'ios' ? 40 : 20),
                transform: [{ translateY }]
            }
        ]}>
            <View style={styles.content}>
                <Ionicons name="cloud-offline" size={20} color="#fff" style={styles.icon} />
                <Text style={styles.text}>No internet connection detected</Text>
            </View>
        </Animated.View>
    );
}

const styles = StyleSheet.create({
    container: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        backgroundColor: '#dc2626', // Tailwind red-600
        zIndex: 99999, // Ensure it's above everything including navigation and modals
        elevation: 10, // For Android
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 3.84,
    },
    content: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 10,
        paddingHorizontal: 20,
    },
    icon: {
        marginRight: 8,
    },
    text: {
        color: '#fff',
        fontSize: 14,
        fontWeight: '600',
        textAlign: 'center',
    }
});
