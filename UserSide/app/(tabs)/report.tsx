import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View,
    Text,
    ScrollView,
    TextInput,
    Button,
    TouchableOpacity,
    Image,
    Pressable,
    Alert,
    StyleSheet,
    Modal,
    ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';

import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BACKEND_URL } from '../../config/backend';
import AnonymousGuidelinesModal from '../../components/AnonymousGuidelinesModal';

// Conditional WebView import for native platforms only
let WebView: any = null;
if (Platform.OS !== 'web') {
    try {
        const { WebView: RNWebView } = require('react-native-webview');
        WebView = RNWebView;
    } catch (e) {
        console.log('WebView not available on this platform');
    }
}


import EnhancedLocationPicker from '../../components/EnhancedLocationPicker';
import UpdateSuccessDialog from '../../components/UpdateSuccessDialog';
import FlagNotificationToast from '../../components/FlagNotificationToast';
import { reportService } from '../../services/reportService';
import { notificationService, type Notification } from '../../services/notificationService';
import { useUser } from '../../contexts/UserContext';
import { Link } from 'expo-router';
import styles from './styles';
import { validateDavaoLocation } from '../../utils/geofence';



// Define crime categories with subcategories and pre-defined complaint texts
const CRIME_CATEGORIES: Record<string, Record<string, string[]>> = {
    'Crimes Against Person': {
        'Physical Assault': [
            'I was physically attacked by an unknown individual.',
            'I witnessed a physical altercation between two or more individuals.',
            'I was assaulted by someone I know without provocation.',
            'A physical fight broke out and people were injured.'
        ],
        'Domestic Violence': [
            'I am experiencing physical violence from a family member.',
            'I am being emotionally or psychologically abused at home.',
            'I am being threatened with harm by a relative or partner.',
            'I witnessed domestic violence in my neighborhood.'
        ],
        'Robbery/Holdup': [
            'I was robbed at gunpoint/knifepoint.',
            'My personal belongings were forcibly taken from me.',
            'I witnessed a robbery happening in a public place.',
            'A group of individuals held someone up and took their valuables.'
        ],
        'Threats/Intimidation': [
            'I received death threats from an individual.',
            'I am being intimidated and threatened to remain silent.',
            'Someone threatened me with a weapon.',
            'I am being threatened online and in person.'
        ],
        'Sexual Harassment': [
            'I experienced unwanted sexual advances or comments.',
            'I was touched inappropriately without consent.',
            'I am being harassed with sexual remarks at work/school.',
            'I received explicit messages or images without consent.'
        ],
        'Kidnapping/Abduction': [
            'A person was taken against their will.',
            'A child was abducted from a public area.',
            'I witnessed someone being forcibly taken.',
            'A family member has been kidnapped and ransom is demanded.'
        ],
        'Stabbing/Shooting Incident': [
            'I witnessed a stabbing incident.',
            'I heard gunshots and saw a victim.',
            'Someone was attacked with a bladed weapon.',
            'A shooting occurred in a populated area.'
        ],
        'Murder/Homicide': [
            'I found a deceased person under suspicious circumstances.',
            'I witnessed a killing in progress.',
            'I have information about a murder case.',
            'A dead body was discovered in my area.'
        ]
    },
    'Property-Related Crimes': {
        'Theft/Pickpocketing': [
            'My wallet/phone was stolen without my knowledge.',
            'I caught someone trying to steal from me.',
            'My belongings were taken while I was distracted.',
            'Theft occurred in a crowded area.'
        ],
        'House Break-in/Burglary': [
            'My house was broken into while we were away.',
            'Someone entered my property without permission and stole items.',
            'I discovered signs of forced entry in my home.',
            'Valuables were taken from inside my residence.'
        ],
        'Vehicle Theft (Carnapping)': [
            'My car/motorcycle was stolen.',
            'I witnessed a vehicle being taken without the owner\'s consent.',
            'My vehicle was forcibly taken from me.',
            'An abandoned stolen vehicle was found.'
        ],
        'Vandalism': [
            'Someone damaged my property deliberately.',
            'My car was scratched or broken into.',
            'Public property was defaced or destroyed.',
            'My home was vandalized with graffiti or damage.'
        ],
        'Arson': [
            'A fire was intentionally set on property.',
            'I witnessed someone starting a fire.',
            'My property was burned deliberately.',
            'There are signs of arson in my area.'
        ],
        'Scam/Fraud': [
            'I was tricked into sending money to a scammer.',
            'I fell victim to an online scam.',
            'Someone posed as an official to defraud me.',
            'I have evidence of a fraudulent scheme.'
        ],
        'Trespassing': [
            'An unknown person entered my property without permission.',
            'Someone is illegally occupying my land.',
            'I witnessed trespassing on private property.',
            'Unauthorized individuals are using my property.'
        ]
    },
    'Public Safety Incidents': {
        'Fire Emergency': [
            'A fire broke out in my area.',
            'I see smoke or flames coming from a building.',
            'There is an uncontrolled fire nearby.',
            'A fire hazard needs immediate attention.'
        ],
        'Road Accident': [
            'A vehicular accident occurred.',
            'I witnessed a hit-and-run incident.',
            'Multiple vehicles were involved in a collision.',
            'There are injuries from a road accident.'
        ],
        'Medical Emergency': [
            'Someone needs urgent medical attention.',
            'A person collapsed and is unresponsive.',
            'There is a medical emergency in my area.',
            'An ambulance is needed immediately.'
        ],
        'Natural Disaster (Flood, etc.)': [
            'Flooding is affecting my area.',
            'Landslide has occurred nearby.',
            'Strong winds/storm caused damage.',
            'People are stranded due to natural disaster.'
        ],
        'Hazardous Materials': [
            'A chemical spill occurred.',
            'There is a gas leak in my area.',
            'Hazardous waste was dumped illegally.',
            'Dangerous materials are exposed to the public.'
        ],
        'Building Collapse/Structural Danger': [
            'A building has collapsed.',
            'There are signs of structural instability.',
            'A wall/roof is about to collapse.',
            'People may be trapped under debris.'
        ]
    },
    'Public Order Issues': {
        'Public Intoxication': [
            'An intoxicated person is causing disturbance.',
            'Someone is drunk and behaving aggressively.',
            'A person under the influence is endangering others.',
            'Public drinking is happening in a prohibited area.'
        ],
        'Noise Complaint': [
            'Excessive noise is disturbing the peace.',
            'Loud parties are happening late at night.',
            'Construction noise is beyond reasonable hours.',
            'Repeated noise violations from a neighbor.'
        ],
        'Illegal Gambling': [
            'Illegal gambling is happening in my area.',
            'A gambling den is operating nearby.',
            'People are gambling in public spaces.',
            'I have information about an illegal betting operation.'
        ],
        'Loitering/Suspicious Activity': [
            'Suspicious individuals are loitering near my property.',
            'Someone is acting strangely in my neighborhood.',
            'A group is gathering and behaving suspiciously.',
            'I noticed unusual activity that may be criminal.'
        ]
    },
    'Cyber-Related Crimes': {
        'Online Scam/Phishing': [
            'I received a phishing email/message.',
            'Someone impersonated a company to steal my info.',
            'I was scammed through an online platform.',
            'My personal data was used fraudulently.'
        ],
        'Hacking/Unauthorized Access': [
            'My account was hacked.',
            'Someone accessed my system without permission.',
            'I detected unauthorized login attempts.',
            'My data was stolen through hacking.'
        ],
        'Cyberbullying': [
            'I am being harassed online.',
            'Someone is spreading false information about me.',
            'I am receiving threatening messages online.',
            'A person is defaming me on social media.'
        ],
        'Identity Theft': [
            'Someone is using my identity without consent.',
            'My personal information was stolen.',
            'Fraudulent accounts were created using my name.',
            'I discovered unauthorized transactions in my name.'
        ],
        'Online Threats': [
            'I received death threats online.',
            'Someone is threatening me through social media.',
            'I am being blackmailed online.',
            'Threatening messages were sent to my accounts.'
        ],
        'Sextortion/Image-Based Abuse': [
            'Someone is threatening to share my intimate images.',
            'I am being blackmailed with private photos.',
            'My intimate images were shared without consent.',
            'I am a victim of revenge porn.'
        ]
    },
    'Moral and Exploitation Crimes': {
        'Drug-Related Incident': [
            'I witnessed drug use or dealing in my area.',
            'Someone is selling illegal drugs nearby.',
            'A drug den is operating in my neighborhood.',
            'I have information about drug trafficking.'
        ],
        'Human Trafficking': [
            'I suspect human trafficking is happening.',
            'Someone is being held against their will.',
            'I have information about trafficking victims.',
            'A person is being exploited for labor/sex.'
        ],
        'Child Abuse/Exploitation': [
            'A child is being abused or neglected.',
            'I witnessed child exploitation.',
            'A minor is being mistreated.',
            'I have information about child abuse.'
        ],
        'Illegal Firearms/Weapons': [
            'Someone is carrying an illegal firearm.',
            'Illegal weapons are being sold in my area.',
            'I witnessed someone brandishing a weapon.',
            'Firearms were discharged illegally.'
        ]
    },
    'Other/Unknown': {
        'Others': [
            'An incident occurred that doesn\'t fit other categories.',
            'I want to report something unusual.',
            'I have information about a potential crime.',
            'I witnessed something suspicious.'
        ],
        'Unidentified Incident': [
            'I\'m not sure what category this falls under.',
            'Something happened but I can\'t classify it.',
            'I need to report an unclear incident.',
            'There was an incident of unknown nature.'
        ]
    }
};

// Flatten for backward compatibility - get all subcategory names
const CRIME_TYPES = Object.values(CRIME_CATEGORIES).flatMap(category => Object.keys(category));


// ✅ Type for CheckRow props
type CheckRowProps = {
    label: string;
    checked: boolean;
    onToggle: () => void;
};

function CheckRow({ label, checked, onToggle }: CheckRowProps) {
    return (
        <Pressable onPress={onToggle} style={styles.checkboxRow} android_ripple={{ color: '#e5e5e5' }}>
            <Text style={[styles.checkboxText, { flex: 1 }]}>{label}</Text>
            <View style={[styles.checkboxBox, checked && styles.checkboxBoxChecked]}>
                {checked && <Text style={styles.checkboxTick}>✓</Text>}
            </View>
        </Pressable>
    );
}

export default function ReportCrime() {
    // 📊 Performance Timing - Start
    const pageStartTime = React.useRef(Date.now());
    React.useEffect(() => {
        const loadTime = Date.now() - pageStartTime.current;
        console.log(`📊 [Report] Page Load Time: ${loadTime}ms`);
    }, []);
    // 📊 Performance Timing - End

    const { user } = useUser();
    const params = useLocalSearchParams();
    const [title, setTitle] = useState('');
    const [titleError, setTitleError] = useState('');
    const [selectedCrimes, setSelectedCrimes] = useState<string[]>([]);
    const [openedCategories, setOpenedCategories] = useState<string[]>([]);
    const [expandedSubcategories, setExpandedSubcategories] = useState<string[]>([]);
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const [location, setLocation] = useState('');
    const [barangay, setBarangay] = useState('');
    const [barangayId, setBarangayId] = useState<number | null>(null);
    const [streetAddress, setStreetAddress] = useState('');
    const [description, setDescription] = useState('');

    const [showLocationPicker, setShowLocationPicker] = useState(false);
    const [locationCoordinates, setLocationCoordinates] = useState<{ latitude: number; longitude: number } | null>(null);
    const [isAnonymous, setIsAnonymous] = useState(params.anonymous === 'true');
    const [selectedPhotoEvidence, setSelectedPhotoEvidence] = useState<ImagePicker.ImagePickerAsset | null>(null);
    const [selectedVideoEvidence, setSelectedVideoEvidence] = useState<ImagePicker.ImagePickerAsset | null>(null);
    const [showMediaViewer, setShowMediaViewer] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showSuccessDialog, setShowSuccessDialog] = useState(false);
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);
    const [showGuidelinesModal, setShowGuidelinesModal] = useState(false);
    const [showAllGuidelinesModal, setShowAllGuidelinesModal] = useState(false); // For anonymous users clicking "crimes" link
    const [selectedCrimeForGuidelines, setSelectedCrimeForGuidelines] = useState<string>('');

    type ConfirmSnapshot = {
        crimeTypes: string[];
        description: string;
        incidentDateTime: string;
        isAnonymous: boolean;
        locationText: string;
        streetAddress: string;
        barangay: string;
        barangayId: number | null;
        coordinates: { latitude: number; longitude: number } | null;
        requiresEvidence: boolean;
        mediaFiles: Array<{
            fileName?: string;
            fileSize?: number;
            type?: string;
            uri: string;
        }>;
    };

    const [confirmSnapshot, setConfirmSnapshot] = useState<ConfirmSnapshot | null>(null);
    const [flagNotification, setFlagNotification] = useState<Notification | null>(null);
    const [isFlagged, setIsFlagged] = useState(false);
    const [userId, setUserId] = useState<string>('');

    // Crime type guidelines for anonymous reporting
    const getCrimeGuidelines = (crimeType: string): string[] => {
        // Evidence requirements for different crime types
        const EVIDENCE_OPTIONAL_CRIMES = [
            'Threats/Intimidation',
            'Noise Complaint',
            'Loitering/Suspicious Activity',
            'Public Intoxication',
            'Online Threats',
            'Cyberbullying',
            'Online Scam/Phishing',
            'Identity Theft',
            'Others',
            'Unidentified Incident'
        ];

        const requiresEvidence = !EVIDENCE_OPTIONAL_CRIMES.map(c => c.toLowerCase()).includes(crimeType.toLowerCase());

        const baseGuidelines = [
            'Provide accurate and truthful information',
            'Include specific details about time and location',
            'Describe suspects or vehicles if applicable',
            'Do not exaggerate or fabricate details',
        ];

        if (requiresEvidence) {
            baseGuidelines.push('Photo or video evidence is required for this crime type');
        } else {
            baseGuidelines.push('Photo or video evidence is optional but recommended');
        }

        // Add crime-specific guidelines
        const specificGuidelines: Record<string, string[]> = {
            'Physical Assault': ['Note any visible injuries', 'Identify witnesses if possible'],
            'Domestic Violence': ['Your safety is the priority', 'Include any history of incidents'],
            'Robbery/Holdup': ['Note descriptions of suspects', 'List stolen items and their value'],
            'Sexual Harassment': ['Your identity is protected', 'Include any witness information'],
            'Theft/Pickpocketing': ['Describe stolen items clearly', 'Note the time and location precisely'],
            'Vehicle Theft (Carnapping)': ['Include vehicle plate number', 'Note last known location'],
            'Fire Emergency': ['Call 911 first for emergencies', 'Note if anyone is trapped'],
            'Medical Emergency': ['Call 911 first for emergencies', 'Provide exact location'],
        };

        return [...baseGuidelines, ...(specificGuidelines[crimeType] || [])];
    };

    // Load user ID and check flag status when component mounts or comes into focus
    // Polling for real-time restriction status
    useFocusEffect(
        React.useCallback(() => {
            let isActive = true;
            let pollInterval: any;

            const checkRestrictions = async () => {
                if (!userId) return;

                try {
                    // Use the specific endpoint for restrictions check
                    const response = await fetch(`${process.env.EXPO_PUBLIC_API_BASE_URL}/api/users/${userId}/restrictions`);
                    const data = await response.json();

                    if (isActive && data) {
                        const isRestricted = data.isRestricted && (!data.expiresAt || new Date(data.expiresAt) > new Date());

                        // Only update state if it changed to avoid re-renders
                        if (isRestricted !== isFlagged) {
                            setIsFlagged(isRestricted);
                            if (isRestricted) {
                                // If newly restricted, verify if we need to show notification
                                const notifications = await notificationService.getUserNotifications(userId);
                                const flaggedNotification = notifications.find(n => n.type === 'user_flagged');
                                if (flaggedNotification) {
                                    setFlagNotification(flaggedNotification);
                                }
                            } else {
                                // If restriction lifted, clear notification
                                setFlagNotification(null);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error polling restrictions:', error);
                }
            };

            // Initial check
            checkRestrictions();

            // Poll every 2 seconds for real-time updates
            pollInterval = setInterval(checkRestrictions, 2000);

            return () => {
                isActive = false;
                clearInterval(pollInterval);
            };
        }, [userId, isFlagged])
    );

    // Listen for messages from web iframe minimap
    useEffect(() => {
        if (Platform.OS === 'web') {
            const handleMessage = (event: MessageEvent) => {
                if (event.data && event.data.type === 'locationSelected') {
                    const { latitude, longitude } = event.data;

                    // Validate location is within Davao City
                    const validation = validateDavaoLocation(latitude, longitude);
                    if (!validation.isValid) {
                        Alert.alert(
                            'Location Outside Davao City',
                            'We only accept reports within Davao City.\\n\\nThe selected location is outside Davao City boundaries. Please tap on the map within Davao City to proceed with your report.',
                            [
                                {
                                    text: 'OK',
                                    style: 'default'
                                }
                            ]
                        );
                        return; // Don't set invalid location
                    }

                    setLocationCoordinates({
                        latitude,
                        longitude
                    });

                    // Resolve address + barangay for the selected coordinates
                    resolveAndSetLocationFromCoords(latitude, longitude)
                        .catch(err => console.error('Resolve location error:', err));
                }
            };

            window.addEventListener('message', handleMessage);
            return () => window.removeEventListener('message', handleMessage);
        }
    }, []);

    // Debug: Log when showLocationPicker changes
    // Item #6: Auto-pin current location on mount
    useEffect(() => {
        const autoGetCurrentLocation = async () => {
            try {
                console.log('🗺️ Auto-pinning current location...');
                const { status } = await Location.requestForegroundPermissionsAsync();

                if (status === 'granted') {
                    // Start a 10-second timeout race
                    const timeoutPromise = new Promise((_, reject) => {
                        setTimeout(() => reject(new Error('Location fetch timed out')), 10000);
                    });

                    let location;
                    try {
                        location = await Promise.race([
                            Location.getCurrentPositionAsync({
                                accuracy: Location.Accuracy.Balanced
                            }),
                            timeoutPromise
                        ]) as Location.LocationObject;
                    } catch (highAccuracyError) {
                        console.log('⚠️ High accuracy fetch failed or timed out, trying last known position...');
                        // Fallback to last known position
                        location = await Location.getLastKnownPositionAsync();
                        if (!location) {
                            throw new Error('Could not determine location even with fallback');
                        }
                    }

                    const { latitude, longitude } = location.coords;
                    console.log(`📍 Auto-pinned location: ${latitude}, ${longitude}`);

                    await resolveAndSetLocationFromCoords(latitude, longitude);
                } else {
                    console.log('⚠️ Location permission not granted');
                }
            } catch (error) {
                console.error('Error auto-pinning location:', error);
            }
        };

        autoGetCurrentLocation();
    }, []); // Run once on mount

    useEffect(() => {
        console.log('📍 showLocationPicker changed:', showLocationPicker);
    }, [showLocationPicker]);

    const deriveStreetFromAddress = (address?: string) => {
        const raw = (address || '').trim();
        if (!raw) return '';
        if (raw.toLowerCase().startsWith('location:')) return '';
        const first = raw.split(',')[0]?.trim() || '';
        // Avoid returning pure coordinates as street
        if (/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(first)) return '';
        return first;
    };

    const resolveAndSetLocationFromCoords = async (latitude: number, longitude: number) => {
        setLocationCoordinates({ latitude, longitude });

        // 1) Get barangay by coordinates (authoritative for this app)
        let barangayName = '';
        let barangayIdLocal: number | null = null;
        try {
            const brgyRes = await fetch(
                `${BACKEND_URL}/api/barangay/by-coordinates?latitude=${latitude}&longitude=${longitude}`
            );
            const brgyData = await brgyRes.json();
            if (brgyData?.success && brgyData?.barangay) {
                barangayName = brgyData.barangay.barangay_name || '';
                barangayIdLocal = brgyData.barangay.barangay_id ?? null;
                setBarangay(barangayName);
                setBarangayId(barangayIdLocal);
            }
        } catch (e) {
            console.warn('Barangay lookup failed:', (e as any)?.message || e);
        }

        // 2) Reverse geocode to get a human-friendly street/address
        let street = '';
        let fullAddress = '';
        try {
            const apiUrl = (Platform.OS === 'web' && typeof window !== 'undefined')
                ? `${window.location.protocol}//${window.location.host}/api/location/reverse`
                : `${BACKEND_URL}/api/location/reverse`;

            const res = await fetch(`${apiUrl}?lat=${latitude}&lon=${longitude}`);
            const addressData = await res.json();
            if (addressData?.success) {
                fullAddress = (addressData.address || addressData.display_name || '').trim();
                street = deriveStreetFromAddress(fullAddress);
            }
        } catch (e) {
            console.warn('Reverse geocode failed:', (e as any)?.message || e);
        }

        // 3) Ensure streetAddress is never empty (required for submit)
        if (!street) {
            if (barangayName) street = `Near ${barangayName}`;
            else street = 'Location auto-detected';
        }

        setStreetAddress(street);

        // Keep a full address string for payload/confirmation, even if UI doesn't preview it
        const composed = [street, barangayName, 'Davao City'].filter(Boolean).join(', ');
        setLocation(composed || fullAddress || '');
    };

    // ✅ Toggle function with correct typing
    const toggleCrimeType = (crime: string) => {
        setSelectedCrimes((prev) =>
            prev.includes(crime) ? prev.filter((c) => c !== crime) : [...prev, crime]
        );
    };

    const handleUseLocation = () => {
        console.log('🗺️ Opening location picker...');
        setShowLocationPicker(true);
        console.log('✅ showLocationPicker set to true');
    };

    const handleLocationSelect = (data: {
        barangay: string;
        barangay_id: number;
        street_address: string;
        full_address: string;
        latitude: number;
        longitude: number;
    }) => {
        console.log('📥 handleLocationSelect called in report.tsx');
        console.log('✅ Location data received:', data);
        console.log('📍 Coordinates:', {
            latitude: data.latitude,
            longitude: data.longitude
        });

        // Validate location is within Davao City
        const validation = validateDavaoLocation(data.latitude, data.longitude);
        if (!validation.isValid) {
            Alert.alert(
                'Location Outside Davao City',
                'We only accept reports within Davao City.\n\nThe selected location is outside Davao City boundaries. Please select a different location within Davao City to proceed with your report.',
                [
                    {
                        text: 'Select Different Location',
                        onPress: () => {
                            // Keep the location picker open or reopen it
                            setShowLocationPicker(true);
                        },
                        style: 'default'
                    }
                ],
                { cancelable: false }
            );
            return; // Don't proceed with invalid location
        }

        // Set location display as full address
        setLocation(data.full_address);
        setBarangay(data.barangay);
        setBarangayId(data.barangay_id);
        setStreetAddress(data.street_address);
        setLocationCoordinates({
            latitude: data.latitude,
            longitude: data.longitude
        });

        console.log('✅ State updated:', {
            location: data.full_address,
            barangay: data.barangay,
            barangayId: data.barangay_id,
            streetAddress: data.street_address,
            coordinates: {
                latitude: data.latitude,
                longitude: data.longitude
            }
        });

        setShowLocationPicker(false);
        console.log('✅ Location picker closed');
    };

    const handleLocationPickerClose = () => {
        setShowLocationPicker(false);
    };

    const pickEvidence = async (kind: 'photo' | 'video') => {
        const permissionResult = await ImagePicker.requestMediaLibraryPermissionsAsync();

        if (!permissionResult.granted) {
            Alert.alert('Permission Required', 'Please grant access to your media library to upload evidence.');
            return;
        }

        const result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: kind === 'photo' ? ['images'] : ['videos'],
            allowsEditing: kind === 'photo',
            quality: 1,
        });

        if (!result.canceled && result.assets && result.assets[0]) {
            const asset = result.assets[0];

            const maxSizeInBytes = 25 * 1024 * 1024;
            if (asset.fileSize && asset.fileSize > maxSizeInBytes) {
                Alert.alert('File Too Large', 'Your file is too big. Please select a file smaller than 25MB.');
                return;
            }

            if (kind === 'photo') {
                setSelectedPhotoEvidence(asset);
            } else {
                setSelectedVideoEvidence(asset);
            }
        }
    };

    const removePhotoEvidence = () => setSelectedPhotoEvidence(null);
    const removeVideoEvidence = () => setSelectedVideoEvidence(null);

    // Helper function to format file sizes
    const formatBytes = (bytes?: number) => {
        if (!bytes || bytes <= 0) return 'Unknown size';
        const units = ['B', 'KB', 'MB', 'GB'];
        const exp = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / Math.pow(1024, exp);
        return `${value.toFixed(value >= 10 || exp === 0 ? 0 : 1)} ${units[exp]}`;
    };




    const handleSubmit = async () => {
        console.log('🔍 Validating report submission...');

        // Check if user is flagged
        if (isFlagged) {
            Alert.alert(
                'Account Flagged',
                'Your account has been flagged. You are unable to submit new reports until the flag is lifted by an administrator.',
                [{ text: 'OK' }]
            );
            return;
        }

        console.log('Current state:', {
            title: title.trim(),
            selectedCrimes,
            description: description.trim(),
            user,
            barangay,
            barangayId,
            streetAddress: streetAddress.trim(),
            locationCoordinates
        });

        // Validation - Check all required fields
        // Title validation removed - will be auto-generated

        if (selectedCrimes.length === 0) {
            console.log('❌ Validation failed: No crime types');
            Alert.alert('Missing Information', 'Please select at least one crime type.');
            return;
        }
        console.log('✅ Crime types validated');


        // Item 10: Sensitive Content Detection
        const sensitiveWords = ['fuck', 'shit', 'bitch', 'asshole', 'gago', 'putangina', 'leche', 'bobo', 'tanga', 'piste', 'yawa', 'inutil'];
        const textToCheck = (description || '').toLowerCase();
        const foundSensitive = sensitiveWords.filter(word => textToCheck.includes(word));

        if (foundSensitive.length > 0) {
            console.log('❌ Validation failed: Sensitive content detected');
            Alert.alert(
                'Sensitive Content Detected',
                `Your report contains inappropriate language ("${foundSensitive[0]}").\n\nPlease remove it to maintain community guidelines.`
            );
            return;
        }

        if (!description.trim()) {
            console.log('❌ Validation failed: No description');
            Alert.alert('Missing Information', 'Please describe what happened in detail.');
            return;
        }
        console.log('✅ Description validated');

        // ⚠️ Evidence validation - Required for most crime types
        // These subcategories don't require mandatory evidence
        const EVIDENCE_OPTIONAL_CRIMES = [
            'Threats/Intimidation',
            'Noise Complaint',
            'Loitering/Suspicious Activity',
            'Public Intoxication',
            'Online Threats',
            'Cyberbullying',
            'Online Scam/Phishing',
            'Identity Theft',
            'Others',
            'Unidentified Incident'
        ];

        const selectedCrimesLower = selectedCrimes.map(c => (c || '').toLowerCase().trim());
        const requiresEvidence = !selectedCrimesLower.some(crime =>
            EVIDENCE_OPTIONAL_CRIMES.map(c => c.toLowerCase()).includes(crime)
        );

        // Evidence is required: EITHER a photo OR a video (not both)
        if (requiresEvidence && !selectedPhotoEvidence && !selectedVideoEvidence) {
            console.log('❌ Validation failed: Evidence required but not provided');
            Alert.alert(
                'Evidence Required',
                `Please upload a photo OR a video of this incident.\n\nThis helps police verify and respond faster to your report.\n\nEvidence is required for: ${selectedCrimes.join(', ')}`
            );
            return;
        }

        if (selectedPhotoEvidence || selectedVideoEvidence) {
            console.log('✅ Evidence provided');
        } else {
            console.log('ℹ️ Evidence optional for this crime type');
        }



        // Check if user is logged in
        console.log('👤 Current user from context:', user);
        console.log('🆔 User ID:', user?.id);

        if (!isAnonymous && (!user || !user.id)) {
            console.log('❌ Validation failed: User not logged in and not anonymous');
            Alert.alert('Authentication Required', 'You must be logged in to submit a report.');
            console.error('User not logged in:', user);
            return;
        }
        console.log('✅ User authenticated');

        // Street address and coordinates are required, barangay is optional (will be determined server-side)
        if (!streetAddress.trim()) {
            console.log('❌ Validation failed: No street address');
            Alert.alert(
                'Missing Information',
                'Please enter a street address.\n\nClick "Change Location" to add your street name, building, and house number.'
            );
            return;
        }
        console.log('✅ Street address validated');

        if (barangay && barangayId) {
            console.log('✅ Barangay selected:', barangay);
        } else {
            console.log('⚠️ No barangay selected - will be determined server-side from coordinates');
        }

        if (!locationCoordinates) {
            console.log('❌ Validation failed: No coordinates');
            Alert.alert(
                'Missing Information',
                'Location coordinates are missing. Please use the location picker to set a valid location.'
            );
            return;
        }
        console.log('✅ Location coordinates exist');

        // Validate coordinates are not 0,0
        if (locationCoordinates.latitude === 0 && locationCoordinates.longitude === 0) {
            console.log('❌ Validation failed: Invalid coordinates (0,0)');
            Alert.alert(
                'Invalid Location',
                'The location has invalid coordinates. Please select a valid location using the map.'
            );
            return;
        }
        console.log('✅ Coordinates validated');

        // Final geofence check - ensure location is within Davao City
        console.log('🚧 Final geofence check for coordinates:', locationCoordinates);
        const geofenceValidation = validateDavaoLocation(
            locationCoordinates.latitude,
            locationCoordinates.longitude
        );

        if (!geofenceValidation.isValid) {
            console.log('❌ Final geofence check failed:', geofenceValidation.errorMessage);
            Alert.alert(
                'We Only Accept Reports Within Davao City',
                geofenceValidation.errorMessage ||
                'The selected location is outside Davao City boundaries.\n\nPlease go back and select a location within Davao City to proceed with your report.'
            );
            return;
        }
        console.log('✅ Final geofence check passed - location is within Davao City');

        console.log('✅ All validations passed');
        console.log('📢 About to show confirmation...');

        // Keep incident datetime logic consistent with submit payload (Asia/Manila, UTC+8)
        const getManilaIncidentDateTime = () => {
            const now = new Date();
            const utcMs = now.getTime() + now.getTimezoneOffset() * 60000;
            const manilaTime = new Date(utcMs + 8 * 3600000);

            const pad = (n: number) => n.toString().padStart(2, '0');
            const year = manilaTime.getFullYear();
            const month = pad(manilaTime.getMonth() + 1);
            const day = pad(manilaTime.getDate());
            const hour = pad(manilaTime.getHours());
            const minute = pad(manilaTime.getMinutes());
            const second = pad(manilaTime.getSeconds());

            return {
                payload: `${year}-${month}-${day} ${hour}:${minute}:${second}`,
                display: `${month}/${day}/${year} ${hour}:${minute}:${second} (Asia/Manila)`
            };
        };

        const incident = getManilaIncidentDateTime();

        setConfirmSnapshot({
            crimeTypes: [...selectedCrimes],
            description: description.trim(),
            incidentDateTime: incident.display,
            isAnonymous,
            locationText: (location || '').trim(),
            streetAddress: (streetAddress || '').trim(),
            barangay: (barangay || '').trim(),
            barangayId,
            coordinates: locationCoordinates
                ? { latitude: locationCoordinates.latitude, longitude: locationCoordinates.longitude }
                : null,
            requiresEvidence,
            mediaFiles: [
                ...(selectedPhotoEvidence
                    ? [{
                        uri: selectedPhotoEvidence.uri,
                        fileName: selectedPhotoEvidence.fileName ?? undefined,
                        fileSize: selectedPhotoEvidence.fileSize ?? undefined,
                        type: selectedPhotoEvidence.type ?? undefined,
                    }]
                    : []),
                ...(selectedVideoEvidence
                    ? [{
                        uri: selectedVideoEvidence.uri,
                        fileName: selectedVideoEvidence.fileName ?? undefined,
                        fileSize: selectedVideoEvidence.fileSize ?? undefined,
                        type: selectedVideoEvidence.type ?? undefined,
                    }]
                    : []),
            ],
        });

        // Show custom confirmation modal
        setShowConfirmDialog(true);
    };

    const submitReportData = async () => {
        try {
            setIsSubmitting(true);
            console.log('\n' + '='.repeat(50));
            console.log('📤 Starting report submission...');

            // Format the incident date and time
            // Automatically get current time in Asia/Manila (UTC+8)
            const now = new Date();
            const utcMs = now.getTime() + now.getTimezoneOffset() * 60000;
            const manilaTime = new Date(utcMs + 8 * 3600000);

            const pad = (n: number) => n.toString().padStart(2, '0');
            const year = manilaTime.getFullYear();
            const month = pad(manilaTime.getMonth() + 1);
            const day = pad(manilaTime.getDate());
            const hour = pad(manilaTime.getHours());
            const minute = pad(manilaTime.getMinutes());
            const second = pad(manilaTime.getSeconds());

            const incidentDateTime = `${year}-${month}-${day} ${hour}:${minute}:${second}`;

            // Prepare report data (title will be auto-generated on backend)
            const reportData = {
                crimeTypes: selectedCrimes,
                description: description.trim(),
                incidentDate: incidentDateTime,
                isAnonymous,
                latitude: locationCoordinates?.latitude ?? null,
                longitude: locationCoordinates?.longitude ?? null,
                location: location.trim(),
                reporters_address: streetAddress.trim(), // Street address for database
                barangay: barangay, // Barangay name
                barangay_id: barangayId, // Barangay ID for linking
                mediaFiles: [
                    ...(selectedPhotoEvidence
                        ? [{
                            uri: selectedPhotoEvidence.uri,
                            fileName: selectedPhotoEvidence.fileName ?? undefined,
                            fileSize: selectedPhotoEvidence.fileSize ?? undefined,
                            type: selectedPhotoEvidence.type ?? undefined,
                        }]
                        : []),
                    ...(selectedVideoEvidence
                        ? [{
                            uri: selectedVideoEvidence.uri,
                            fileName: selectedVideoEvidence.fileName ?? undefined,
                            fileSize: selectedVideoEvidence.fileSize ?? undefined,
                            type: selectedVideoEvidence.type ?? undefined,
                        }]
                        : []),
                ],
                userId: user?.id || '0',
            };

            console.log('📋 Report Data:');
            console.log('   Crime Types:', reportData.crimeTypes);
            console.log('   Location:', reportData.location);
            console.log('   Coordinates:', { lat: reportData.latitude, lng: reportData.longitude });
            console.log('   Has Media:', (reportData.mediaFiles?.length || 0) > 0);
            console.log('   Anonymous:', reportData.isAnonymous);
            console.log('   User ID:', reportData.userId);

            // Submit the report
            console.log('\n🚀 Calling reportService.submitReport()...');
            const response = await reportService.submitReport(reportData);

            console.log('✅ Report submitted successfully!');
            console.log('Response:', response);

            // Store success data to show in dialog
            const locationStr = location.trim() || streetAddress.trim() || barangay?.trim() || 'Location recorded';
            const successMessage = `Your report has been submitted successfully.\n\nLocation: ${locationStr}\n\nIP Address and location have been recorded for security and tracking purposes.`;

            // Show success dialog with IP/location recording message
            setShowSuccessDialog(true);

            // Store the message to display
            (global as any).successMessage = successMessage;

            console.log('='.repeat(50) + '\n');
        } catch (error) {
            console.error('\n❌ Error submitting report:', error);
            console.log('='.repeat(50) + '\n');

            let errorTitle = 'Submission Failed';
            let errorMessage = 'An unexpected error occurred. Please try again.';

            if (error instanceof Error) {
                errorMessage = error.message;

                // Customize title based on error type
                if (errorMessage.toLowerCase().includes('evidence') ||
                    errorMessage.toLowerCase().includes('photo') ||
                    errorMessage.toLowerCase().includes('video')) {
                    errorTitle = 'Evidence Required';
                } else if (errorMessage.toLowerCase().includes('connection') ||
                    errorMessage.toLowerCase().includes('network') ||
                    errorMessage.toLowerCase().includes('timeout')) {
                    errorTitle = 'Connection Error';
                } else if (errorMessage.toLowerCase().includes('authorized') ||
                    errorMessage.toLowerCase().includes('log in')) {
                    errorTitle = 'Authentication Required';
                } else if (errorMessage.toLowerCase().includes('too large') ||
                    errorMessage.toLowerCase().includes('file size')) {
                    errorTitle = 'File Too Large';
                } else if (errorMessage.toLowerCase().includes('server')) {
                    errorTitle = 'Server Error';
                } else if (errorMessage.toLowerCase().includes('missing') ||
                    errorMessage.toLowerCase().includes('required')) {
                    errorTitle = 'Missing Information';
                }
            }

            Alert.alert(
                errorTitle,
                errorMessage,
                [
                    {
                        text: 'OK',
                        style: 'default'
                    }
                ],
                { cancelable: true }
            );
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <View style={{ flex: 1, backgroundColor: '#fff' }}>
            {/* Anonymous Warning Banner */}
            {isAnonymous && (
                <View style={{ backgroundColor: '#fff3cd', padding: 10, alignItems: 'center', borderBottomWidth: 1, borderBottomColor: '#ffeeba' }}>
                    <Text style={{ color: '#856404', fontWeight: 'bold' }}>⚠️ You are reporting anonymously</Text>
                    <Text style={{ color: '#856404', fontSize: 12 }}>You will not receive updates for this report.</Text>
                </View>
            )}
            {/* Flag Notification Toast - Fixed at top */}
            <FlagNotificationToast
                notification={flagNotification}
                onDismiss={() => setFlagNotification(null)}
            />

            <ScrollView
                style={{ flex: 1 }}
                contentContainerStyle={{ paddingBottom: 48, paddingHorizontal: 16 }}
                keyboardShouldPersistTaps="handled"
                showsVerticalScrollIndicator={true}
                scrollEnabled={true}
                nestedScrollEnabled={true}
                bounces={true}
            >

                {/* Header with Back Button and Title */}
                <View style={styles.headerHistory}>
                    <TouchableOpacity onPress={() => router.push('/')}>
                        <Ionicons name="chevron-back" size={24} color="#000" />
                    </TouchableOpacity>
                    <View style={{ flex: 1, alignItems: 'center' }}>
                        <Text style={styles.textTitle}>
                            <Text style={styles.alertWelcome}>Alert</Text>
                            <Text style={styles.davao}>Davao</Text>
                        </Text>
                        <Text style={styles.subheadingCenter}>Report Crime</Text>
                    </View>
                    <View style={{ width: 24 }} />
                </View>

                {/* Title removed - will be auto-generated from crime type + location + date */}

                <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 12, marginTop: 8 }}>
                    <Text style={styles.subheading}>Select the type of </Text>
                    {isAnonymous ? (
                        <TouchableOpacity onPress={(e) => { e.preventDefault(); e.stopPropagation(); setShowAllGuidelinesModal(true); }} hitSlop={{ top: 15, bottom: 15, left: 15, right: 15 }}>
                            <Text style={[styles.subheading, { color: '#0066cc', textDecorationLine: 'underline' }]}>crimes</Text>
                        </TouchableOpacity>
                    ) : (
                        <Link href={{ pathname: "/guidelines", params: { scrollToSection: "crime-types" } }} asChild>
                            <TouchableOpacity hitSlop={{ top: 15, bottom: 15, left: 15, right: 15 }}>
                                <Text style={[styles.subheading, { color: '#0066cc', textDecorationLine: 'underline' }]}>crimes</Text>
                            </TouchableOpacity>
                        </Link>
                    )}
                    <Text style={{ color: '#E63946', fontWeight: '700', fontSize: 18 }}> *</Text>
                </View>
                <View style={[styles.card, isFlagged && { opacity: 0.6, pointerEvents: 'none' }]}>
                    {/* Render each opened category */}
                    {openedCategories.map((category, index) => (
                        <View key={category} style={{ marginBottom: 16 }}>
                            {/* Category Header with Remove Button */}
                            <View style={{
                                backgroundColor: '#1D3357',
                                padding: 12,
                                borderRadius: 8,
                                marginBottom: 8,
                                flexDirection: 'row',
                                justifyContent: 'space-between',
                                alignItems: 'center'
                            }}>
                                <Text style={{
                                    fontSize: 17,
                                    fontWeight: 'bold',
                                    color: '#FFFFFF',
                                    flex: 1
                                }}>
                                    {category}
                                </Text>
                                <TouchableOpacity
                                    onPress={() => {
                                        setOpenedCategories(prev => prev.filter(c => c !== category));
                                        // Also remove any expanded subcategories from this category
                                        const subcats = Object.keys(CRIME_CATEGORIES[category] || {});
                                        setExpandedSubcategories(prev => prev.filter(s => !subcats.includes(s)));
                                        // Remove any selected crimes from this category
                                        setSelectedCrimes(prev => prev.filter(c => !subcats.includes(c)));
                                    }}
                                    style={{ padding: 4 }}
                                >
                                    <Ionicons name="close-circle" size={24} color="#FFFFFF" />
                                </TouchableOpacity>
                            </View>

                            {/* Subcategories for this Category */}
                            <View style={{ paddingLeft: 8 }}>
                                {Object.keys(CRIME_CATEGORIES[category] || {}).map((subcategory) => (
                                    <View key={subcategory} style={{ marginBottom: 8 }}>
                                        <View style={{
                                            flexDirection: 'row',
                                            alignItems: 'center',
                                            paddingVertical: 10,
                                            paddingHorizontal: 8,
                                            backgroundColor: selectedCrimes.includes(subcategory) ? '#E3F2FD' : '#f8f9fa',
                                            borderRadius: 6,
                                            borderWidth: selectedCrimes.includes(subcategory) ? 2 : 1,
                                            borderColor: selectedCrimes.includes(subcategory) ? '#1D3557' : '#ddd',
                                        }}>
                                            <TouchableOpacity
                                                style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }}
                                                onPress={() => toggleCrimeType(subcategory)}
                                            >
                                                <Text style={{
                                                    flex: 1,
                                                    fontSize: 15,
                                                    color: '#333',
                                                    fontWeight: selectedCrimes.includes(subcategory) ? '600' : '400'
                                                }}>
                                                    {subcategory}
                                                </Text>
                                                <View style={[styles.checkboxBox, selectedCrimes.includes(subcategory) && styles.checkboxBoxChecked]}>
                                                    {selectedCrimes.includes(subcategory) && <Text style={styles.checkboxTick}>✓</Text>}
                                                </View>
                                            </TouchableOpacity>
                                            {/* Info button for guidelines (especially useful in anonymous mode) */}
                                            {isAnonymous && (
                                                <TouchableOpacity
                                                    style={{
                                                        padding: 6,
                                                        marginLeft: 4,
                                                        backgroundColor: '#E3F2FD',
                                                        borderRadius: 14,
                                                    }}
                                                    onPress={() => {
                                                        setSelectedCrimeForGuidelines(subcategory);
                                                        setShowGuidelinesModal(true);
                                                    }}
                                                >
                                                    <Ionicons name="information-circle" size={20} color="#1D3557" />
                                                </TouchableOpacity>
                                            )}
                                        </View>
                                    </View>
                                ))}
                            </View>
                        </View>
                    ))}

                    {/* Add Category Button */}
                    {openedCategories.length < Object.keys(CRIME_CATEGORIES).length && (
                        <TouchableOpacity
                            style={{
                                borderWidth: 2,
                                borderColor: '#1D3357',
                                borderStyle: 'dashed',
                                borderRadius: 8,
                                padding: 16,
                                alignItems: 'center',
                                backgroundColor: '#f8f9fa',
                                marginBottom: 12
                            }}
                            onPress={() => setShowCategoryModal(true)}
                        >
                            <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                                <Ionicons name="add-circle-outline" size={24} color="#1D3357" />
                                <Text style={{ marginLeft: 8, fontSize: 16, fontWeight: '600', color: '#1D3357' }}>
                                    {openedCategories.length === 0 ? 'Add Category' : 'Add Another Category'}
                                </Text>
                            </View>
                        </TouchableOpacity>
                    )}

                    {/* Helper Message */}
                    <View style={{ marginTop: 4, paddingHorizontal: 12, paddingVertical: 8, backgroundColor: '#f5f5f5', borderRadius: 8, borderLeftWidth: 4, borderLeftColor: '#1D3357' }}>
                        <Text style={{ fontSize: 13, color: '#666', lineHeight: 18 }}>
                            Not sure which category to choose? If you&apos;re confused, please check the definitions above for guidance.
                        </Text>
                    </View>
                </View>

                <View style={{ flexDirection: 'row', alignItems: 'center', marginTop: 16, marginBottom: 8 }}>
                    <Text style={[styles.label, { marginVertical: 0 }]}>Location</Text>
                    <Text style={{ color: '#E63946', fontWeight: '700', fontSize: 16 }}> *</Text>
                </View>

                {/* Minimal Location Info (no map/coordinate preview) */}
                <View style={{
                    backgroundColor: '#f5f5f5',
                    padding: 12,
                    borderRadius: 8,
                    marginBottom: 12,
                }}>
                    <Text style={{ fontSize: 13, color: '#1D3557', fontWeight: '600' }}>
                        {barangay ? `Barangay: ${barangay}` : 'Barangay: Detecting...'}
                    </Text>
                    <Text style={{ fontSize: 13, color: '#666', marginTop: 4 }}>
                        {streetAddress ? `Street: ${streetAddress}` : 'Street: Detecting...'}
                    </Text>
                </View>

                <TouchableOpacity style={styles.locationButton} onPress={handleUseLocation}>
                    <Ionicons name="location" size={18} color="#fff" style={{ marginRight: 8 }} />
                    <Text style={styles.locationButtonText}>
                        Change Location
                    </Text>
                </TouchableOpacity>

                <View style={{ flexDirection: 'row', alignItems: 'center', marginTop: 16, marginBottom: 8 }}>
                    <Text style={[styles.label, { marginVertical: 0 }]}>Description</Text>
                    <Text style={{ color: '#E63946', fontWeight: '700', fontSize: 16 }}> *</Text>
                </View>

                <TextInput
                    style={[styles.input, styles.textArea]}
                    placeholder="Describe what happened in detail..."
                    value={description}
                    onChangeText={setDescription}
                    multiline
                />

                {/* Pre-defined Complaint Texts */}
                {selectedCrimes.length > 0 && (
                    <View style={{ marginTop: 12, marginBottom: 16 }}>
                        <Text style={{ fontSize: 14, fontWeight: '600', color: '#1D3557', marginBottom: 8 }}>
                            💡 Quick fill suggestions (tap to use):
                        </Text>
                        <ScrollView
                            horizontal
                            showsHorizontalScrollIndicator={false}
                            style={{ marginHorizontal: -16 }}
                            contentContainerStyle={{ paddingHorizontal: 16, gap: 8 }}
                        >
                            {selectedCrimes.flatMap(crimeType => {
                                // Find the category that contains this crime type
                                for (const [categoryName, subcategories] of Object.entries(CRIME_CATEGORIES)) {
                                    if (subcategories[crimeType]) {
                                        return subcategories[crimeType].map((text, idx) => ({
                                            text,
                                            key: `${crimeType}-${idx}`
                                        }));
                                    }
                                }
                                return [];
                            }).slice(0, 8).map(({ text, key }) => (
                                <TouchableOpacity
                                    key={key}
                                    style={{
                                        backgroundColor: '#f0f7ff',
                                        paddingVertical: 10,
                                        paddingHorizontal: 14,
                                        borderRadius: 20,
                                        borderWidth: 1,
                                        borderColor: '#1D3557',
                                        maxWidth: 280,
                                    }}
                                    onPress={() => setDescription(text)}
                                >
                                    <Text style={{ fontSize: 13, color: '#1D3557' }} numberOfLines={2}>
                                        {text}
                                    </Text>
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                        <Text style={{ fontSize: 11, color: '#888', marginTop: 8, fontStyle: 'italic' }}>
                            These are suggestions only. You can edit or write your own description above.
                        </Text>
                    </View>
                )}

                <View style={{ flexDirection: 'row', alignItems: 'center', marginTop: 16, marginBottom: 8 }}>
                    <Text style={[styles.label, { marginVertical: 0 }]}>Evidence (Photo or Video)</Text>
                    <Text style={{ color: '#E63946', fontWeight: '700', fontSize: 16 }}> *</Text>
                </View>
                {/* Evidence Warning */}
                <View style={{
                    backgroundColor: '#fff3cd',
                    borderLeftWidth: 4,
                    borderLeftColor: '#ffc107',
                    padding: 10,
                    borderRadius: 6,
                    marginBottom: 12
                }}>
                    <View style={{ flexDirection: 'row', alignItems: 'flex-start' }}>
                        <Ionicons name="warning" size={16} color="#856404" style={{ marginRight: 6, marginTop: 2 }} />
                        <Text style={{ fontSize: 12, color: '#856404', flex: 1, lineHeight: 18 }}>
                            <Text style={{ fontWeight: '700' }}>Important:</Text> Only attach relevant and appropriate evidence.
                            Do not upload sensitive or explicit content, including nudity, sexual content, or graphic violence
                            unless directly related to the crime being reported.
                        </Text>
                    </View>
                </View>

                <View style={{ flexDirection: 'row', gap: 12 }}>
                    <TouchableOpacity
                        style={[styles.mediaButton, { flex: 1 }, isFlagged && { opacity: 0.6 }]}
                        onPress={isFlagged ? undefined : () => pickEvidence('photo')}
                    >
                        <Ionicons name="image-outline" size={24} color="#1D3557" />
                        <Text style={styles.mediaButtonText}>Select Photo</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[styles.mediaButton, { flex: 1 }, isFlagged && { opacity: 0.6 }]}
                        onPress={isFlagged ? undefined : () => pickEvidence('video')}
                    >
                        <Ionicons name="videocam-outline" size={24} color="#1D3557" />
                        <Text style={styles.mediaButtonText}>Select Video</Text>
                    </TouchableOpacity>
                </View>

                {(selectedPhotoEvidence || selectedVideoEvidence) && (
                    <View style={{ marginTop: 12 }}>
                        {selectedPhotoEvidence && (
                            <View style={styles.mediaPreviewContainer}>
                                <TouchableOpacity style={{ flex: 1, flexDirection: 'row', alignItems: 'center' }} onPress={() => setShowMediaViewer(true)}>
                                    <View style={{
                                        width: 80,
                                        height: 80,
                                        borderRadius: 8,
                                        overflow: 'hidden',
                                        marginRight: 12,
                                        backgroundColor: '#f0f0f0',
                                        borderWidth: 1,
                                        borderColor: '#ddd'
                                    }}>
                                        <Image
                                            source={{ uri: selectedPhotoEvidence.uri }}
                                            style={{ width: '100%', height: '100%' }}
                                            resizeMode="cover"
                                        />
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={styles.mediaName}>{selectedPhotoEvidence.fileName || 'Selected Photo'}</Text>
                                        <Text style={styles.mediaSize}>{formatBytes(selectedPhotoEvidence.fileSize)}</Text>
                                        <Text style={{ color: '#1D3557', marginTop: 4, fontSize: 12 }}>Tap to view</Text>
                                    </View>
                                </TouchableOpacity>
                                <TouchableOpacity style={styles.removeButton} onPress={removePhotoEvidence}>
                                    <Ionicons name="close-circle" size={24} color="#dc3545" />
                                </TouchableOpacity>
                            </View>
                        )}

                        {selectedVideoEvidence && (
                            <View style={styles.mediaPreviewContainer}>
                                <View style={{ flex: 1, flexDirection: 'row', alignItems: 'center' }}>
                                    <View style={{
                                        width: 80,
                                        height: 80,
                                        borderRadius: 8,
                                        marginRight: 12,
                                        backgroundColor: '#f0f0f0',
                                        justifyContent: 'center',
                                        alignItems: 'center',
                                        borderWidth: 1,
                                        borderColor: '#ddd'
                                    }}>
                                        <Ionicons name="videocam" size={32} color="#1D3557" />
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={styles.mediaName}>{selectedVideoEvidence.fileName || 'Selected Video'}</Text>
                                        <Text style={styles.mediaSize}>{formatBytes(selectedVideoEvidence.fileSize)}</Text>
                                    </View>
                                </View>
                                <TouchableOpacity style={styles.removeButton} onPress={removeVideoEvidence}>
                                    <Ionicons name="close-circle" size={24} color="#dc3545" />
                                </TouchableOpacity>
                            </View>
                        )}
                    </View>
                )}

                {/* Full-screen photo viewer */}
                {showMediaViewer && selectedPhotoEvidence && (
                    <Modal
                        transparent
                        visible={showMediaViewer}
                        animationType="fade"
                        onRequestClose={() => setShowMediaViewer(false)}
                    >
                        <View style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.9)', justifyContent: 'center', alignItems: 'center' }}>
                            <TouchableOpacity
                                onPress={() => setShowMediaViewer(false)}
                                style={{ position: 'absolute', top: 50, right: 20, zIndex: 10, padding: 10 }}
                            >
                                <Ionicons name="close-circle" size={36} color="#fff" />
                            </TouchableOpacity>

                            <Image
                                source={{ uri: selectedPhotoEvidence.uri }}
                                style={{ width: '95%', height: '70%' }}
                                resizeMode="contain"
                                onError={(e) => console.error('Image load error:', e.nativeEvent.error)}
                                onLoad={() => console.log('✅ Image loaded successfully')}
                            />

                            <Text style={{ color: '#fff', marginTop: 16, fontSize: 14 }}>
                                {selectedPhotoEvidence.fileName || 'Selected Photo'}
                            </Text>
                        </View>
                    </Modal>
                )}

                {/* Category Selection Modal */}
                <Modal
                    transparent
                    visible={showCategoryModal}
                    animationType="fade"
                    onRequestClose={() => setShowCategoryModal(false)}
                >
                    <View style={styles.modalOverlay}>
                        <View style={[styles.modalContent, { padding: 0 }]}>
                            <View style={{
                                padding: 16,
                                borderBottomWidth: 1,
                                borderBottomColor: '#eee',
                                flexDirection: 'row',
                                justifyContent: 'space-between',
                                alignItems: 'center'
                            }}>
                                <Text style={{ fontSize: 18, fontWeight: 'bold', color: '#1D3357' }}>Select Category</Text>
                                <TouchableOpacity onPress={() => setShowCategoryModal(false)}>
                                    <Ionicons name="close" size={24} color="#666" />
                                </TouchableOpacity>
                            </View>
                            <ScrollView style={{ maxHeight: 300 }}>
                                {Object.keys(CRIME_CATEGORIES)
                                    .filter(category => !openedCategories.includes(category))
                                    .map((category) => (
                                        <TouchableOpacity
                                            key={category}
                                            style={{
                                                padding: 16,
                                                borderBottomWidth: 1,
                                                borderBottomColor: '#f0f0f0',
                                                backgroundColor: '#fff',
                                                flexDirection: 'row',
                                                justifyContent: 'space-between',
                                                alignItems: 'center'
                                            }}
                                            onPress={() => {
                                                setOpenedCategories(prev => [...prev, category]);
                                                setShowCategoryModal(false);
                                            }}
                                        >
                                            <Text style={{
                                                fontSize: 16,
                                                color: '#333',
                                                fontWeight: '400'
                                            }}>
                                                {category}
                                            </Text>
                                            <Ionicons name="add" size={20} color="#1D3357" />
                                        </TouchableOpacity>
                                    ))}
                            </ScrollView>
                        </View>
                    </View>
                </Modal>



                {/* Confirmation Dialog */}
                <Modal
                    visible={showConfirmDialog}
                    transparent={true}
                    animationType="fade"
                    onRequestClose={() => {
                        setShowConfirmDialog(false);
                        setConfirmSnapshot(null);
                    }}
                >
                    <View style={confirmStyles.overlay}>
                        <View style={confirmStyles.dialog}>
                            <View style={confirmStyles.iconContainer}>
                                <Ionicons name="shield-checkmark" size={60} color="#1D3557" />
                            </View>

                            <Text style={confirmStyles.title}>Confirm Submission</Text>

                            <Text style={confirmStyles.message}>
                                Your <Text style={confirmStyles.highlight}>IP address</Text> will be recorded for security and tracking purposes.
                            </Text>

                            <Text style={confirmStyles.subMessage}>
                                This helps ensure accountability and assists law enforcement in responding to reports.
                            </Text>

                            <View style={confirmStyles.detailsContainer}>
                                <Text style={confirmStyles.detailsTitle}>Review your report details</Text>
                                <ScrollView style={confirmStyles.detailsScroll} contentContainerStyle={confirmStyles.detailsScrollContent}>
                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Crime type(s)</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {(confirmSnapshot?.crimeTypes?.length ? confirmSnapshot.crimeTypes : selectedCrimes).join(', ') || 'N/A'}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Description</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {confirmSnapshot?.description ?? description.trim()}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Incident time</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {confirmSnapshot?.incidentDateTime || 'Will be recorded at submission time (Asia/Manila)'}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Anonymous</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {(confirmSnapshot?.isAnonymous ?? isAnonymous) ? 'Yes' : 'No'}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Location</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {(
                                                confirmSnapshot?.locationText ||
                                                location.trim() ||
                                                streetAddress.trim() ||
                                                barangay?.trim() ||
                                                'Location recorded'
                                            )}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Street address</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {confirmSnapshot?.streetAddress || streetAddress.trim()}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Barangay</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {(confirmSnapshot?.barangay || barangay?.trim())
                                                ? `${confirmSnapshot?.barangay || barangay?.trim()}${(confirmSnapshot?.barangayId ?? barangayId) ? ` (ID: ${confirmSnapshot?.barangayId ?? barangayId})` : ''}`
                                                : 'Auto-detected server-side'}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Coordinates</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {(() => {
                                                const coords = confirmSnapshot?.coordinates || locationCoordinates;
                                                if (!coords) return 'N/A';
                                                return `${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)}`;
                                            })()}
                                        </Text>
                                    </View>

                                    <View style={confirmStyles.detailRow}>
                                        <Text style={confirmStyles.detailLabel}>Evidence</Text>
                                        <Text style={confirmStyles.detailValue}>
                                            {(() => {
                                                const mediaFiles = (confirmSnapshot?.mediaFiles && confirmSnapshot.mediaFiles.length > 0)
                                                    ? confirmSnapshot.mediaFiles
                                                    : [
                                                        ...(selectedPhotoEvidence ? [{
                                                            uri: selectedPhotoEvidence.uri,
                                                            fileName: selectedPhotoEvidence.fileName ?? undefined,
                                                            fileSize: selectedPhotoEvidence.fileSize ?? undefined,
                                                            type: selectedPhotoEvidence.type ?? undefined,
                                                        }] : []),
                                                        ...(selectedVideoEvidence ? [{
                                                            uri: selectedVideoEvidence.uri,
                                                            fileName: selectedVideoEvidence.fileName ?? undefined,
                                                            fileSize: selectedVideoEvidence.fileSize ?? undefined,
                                                            type: selectedVideoEvidence.type ?? undefined,
                                                        }] : []),
                                                    ];

                                                if (!mediaFiles || mediaFiles.length === 0) {
                                                    const required = confirmSnapshot?.requiresEvidence ?? false;
                                                    return required ? 'Required (missing photo + video)' : 'None (optional for selected crime types)';
                                                }

                                                return mediaFiles
                                                    .map((m) => {
                                                        const name = m.fileName || 'Selected file';
                                                        const type = m.type ? ` • ${m.type}` : '';
                                                        const size = m.fileSize ? ` • ${formatBytes(m.fileSize)}` : '';
                                                        return `${name}${type}${size}`;
                                                    })
                                                    .join(' | ');
                                            })()}
                                        </Text>
                                    </View>
                                </ScrollView>
                            </View>

                            <Text style={confirmStyles.question}>Do you want to proceed?</Text>

                            <View style={confirmStyles.buttonContainer}>
                                <TouchableOpacity
                                    style={[confirmStyles.button, confirmStyles.cancelButton]}
                                    onPress={() => {
                                        setShowConfirmDialog(false);
                                        setConfirmSnapshot(null);
                                    }}
                                    disabled={isSubmitting}
                                >
                                    <Text style={confirmStyles.cancelButtonText}>Cancel</Text>
                                </TouchableOpacity>

                                <TouchableOpacity
                                    style={[confirmStyles.button, confirmStyles.submitButton]}
                                    onPress={() => {
                                        console.log('🚀 User confirmed submission, calling submitReportData...');
                                        setShowConfirmDialog(false);
                                        setConfirmSnapshot(null);
                                        submitReportData();
                                    }}
                                    disabled={isSubmitting}
                                >
                                    <Text style={confirmStyles.submitButtonText}>Submit Report</Text>
                                    <Ionicons name="checkmark-circle" size={20} color="#fff" style={{ marginLeft: 8 }} />
                                </TouchableOpacity>
                            </View>
                        </View>
                    </View>
                </Modal>

                {/* Anonymous mode is set from login screen params */}

                <View style={{ paddingVertical: 20, paddingHorizontal: 16 }}>
                    <TouchableOpacity
                        onPress={handleSubmit}
                        disabled={isSubmitting || isFlagged}
                        style={{
                            backgroundColor: isFlagged ? "#999" : "#1D3557",
                            paddingVertical: 18,
                            paddingHorizontal: 32,
                            borderRadius: 12,
                            alignItems: 'center',
                            justifyContent: 'center',
                            elevation: 4,
                            shadowColor: '#000',
                            shadowOffset: { width: 0, height: 3 },
                            shadowOpacity: 0.3,
                            shadowRadius: 4.65,
                        }}
                    >
                        <Text style={{ color: '#fff', fontSize: 18, fontWeight: '700', letterSpacing: 0.5 }}>
                            {isFlagged ? "Account Flagged - Cannot Submit" : (isSubmitting ? "Submitting..." : "Submit Report")}
                        </Text>
                    </TouchableOpacity>
                </View>

                {isFlagged && (
                    <View style={{
                        backgroundColor: '#fee2e2',
                        borderLeftWidth: 4,
                        borderLeftColor: '#dc2626',
                        padding: 12,
                        marginTop: 12,
                        marginHorizontal: 12,
                        borderRadius: 6,
                    }}>
                        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 8 }}>
                            <Ionicons name="warning" size={18} color="#dc2626" style={{ marginTop: 2 }} />
                            <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 14, fontWeight: '600', color: '#991b1b', marginBottom: 4 }}>
                                    Account Flagged
                                </Text>
                                <Text style={{ fontSize: 13, color: '#7f1d1d', lineHeight: 18 }}>
                                    Your account has been flagged. You are unable to submit new reports until the flag is lifted by an administrator.
                                </Text>
                            </View>
                        </View>
                    </View>
                )}

                {isSubmitting && (
                    <View style={{ alignItems: 'center', marginTop: 16, marginBottom: 16 }}>
                        <ActivityIndicator size="large" color="#1D3557" />
                        <Text style={{ marginTop: 8, color: '#666' }}>Submitting your report...</Text>
                    </View>
                )}

                {/* Success Dialog */}
                {showSuccessDialog && (
                    <UpdateSuccessDialog
                        visible={showSuccessDialog}
                        title="Report Submitted!"
                        message="Your report has been submitted successfully. Thank you for helping make our community safer."
                        okText="Proceed"
                        onOk={async () => {
                            setShowSuccessDialog(false);
                            // Reset form
                            setTitle('');
                            setSelectedCrimes([]);
                            setLocation('');
                            setBarangay('');
                            setBarangayId(null);
                            setStreetAddress('');
                            setDescription('');

                            setLocationCoordinates(null);
                            setIsAnonymous(false);
                            setSelectedPhotoEvidence(null);
                            setSelectedVideoEvidence(null);
                            // Navigate back to appropriate dashboard
                            try {
                                const stored = await AsyncStorage.getItem('userData');
                                const user = stored ? JSON.parse(stored) : null;
                                const role = String(user?.user_role || user?.role || '').toLowerCase();
                                if (role === 'patrol_officer') {
                                    router.replace('/(patrol)/dashboard' as any);
                                } else {
                                    router.replace('/(tabs)');
                                }
                            } catch {
                                router.replace('/(tabs)');
                            }
                        }}
                    />
                )}
            </ScrollView>

            {/* Location Picker Modal - Moved outside ScrollView */}
            {showLocationPicker && (
                <EnhancedLocationPicker
                    visible={showLocationPicker}
                    onClose={handleLocationPickerClose}
                    onLocationSelect={handleLocationSelect}
                    initialLocation={{
                        barangay,
                        barangay_id: typeof barangayId === 'number' ? barangayId : undefined,
                        street_address: streetAddress,
                        full_address: location,
                        latitude: typeof locationCoordinates?.latitude === 'number' ? locationCoordinates.latitude : undefined,
                        longitude: typeof locationCoordinates?.longitude === 'number' ? locationCoordinates.longitude : undefined,
                    }}
                />
            )}

            {/* Crime Type Guidelines Modal for Anonymous Reporting */}
            <Modal
                visible={showGuidelinesModal}
                transparent={true}
                animationType="fade"
                onRequestClose={() => setShowGuidelinesModal(false)}
            >
                <View style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', padding: 20 }}>
                    <View style={{ backgroundColor: 'white', borderRadius: 16, padding: 0, maxHeight: '80%', overflow: 'hidden' }}>
                        {/* Header */}
                        <View style={{
                            backgroundColor: '#1D3557',
                            paddingHorizontal: 20,
                            paddingVertical: 16,
                            flexDirection: 'row',
                            justifyContent: 'space-between',
                            alignItems: 'center'
                        }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }}>
                                <Ionicons name="information-circle" size={24} color="#fff" />
                                <Text style={{ fontSize: 18, fontWeight: '700', color: '#fff', marginLeft: 10 }}>
                                    Reporting Guidelines
                                </Text>
                            </View>
                            <TouchableOpacity onPress={() => setShowGuidelinesModal(false)}>
                                <Ionicons name="close" size={26} color="#fff" />
                            </TouchableOpacity>
                        </View>

                        <ScrollView style={{ paddingHorizontal: 20, paddingVertical: 16 }}>
                            {/* Crime Type Badge */}
                            <View style={{
                                backgroundColor: '#E3F2FD',
                                paddingHorizontal: 14,
                                paddingVertical: 8,
                                borderRadius: 20,
                                alignSelf: 'flex-start',
                                marginBottom: 16
                            }}>
                                <Text style={{ color: '#1D3557', fontWeight: '600', fontSize: 14 }}>
                                    {selectedCrimeForGuidelines}
                                </Text>
                            </View>

                            {/* Anonymous Warning */}
                            <View style={{
                                backgroundColor: '#fff3cd',
                                padding: 14,
                                borderRadius: 10,
                                marginBottom: 20,
                                borderLeftWidth: 4,
                                borderLeftColor: '#ffc107'
                            }}>
                                <View style={{ flexDirection: 'row', alignItems: 'flex-start' }}>
                                    <Ionicons name="eye-off" size={20} color="#856404" style={{ marginRight: 10, marginTop: 2 }} />
                                    <View style={{ flex: 1 }}>
                                        <Text style={{ color: '#856404', fontWeight: '700', marginBottom: 4, fontSize: 14 }}>
                                            Anonymous Reporting
                                        </Text>
                                        <Text style={{ color: '#856404', fontSize: 13, lineHeight: 18 }}>
                                            You will NOT receive updates about your report. Provide accurate details to help police respond effectively.
                                        </Text>
                                    </View>
                                </View>
                            </View>

                            {/* Guidelines List */}
                            <Text style={{ fontSize: 15, fontWeight: '700', color: '#1D3557', marginBottom: 12 }}>
                                Guidelines for "{selectedCrimeForGuidelines}":
                            </Text>

                            {getCrimeGuidelines(selectedCrimeForGuidelines).map((guideline, index) => (
                                <View
                                    key={index}
                                    style={{
                                        flexDirection: 'row',
                                        alignItems: 'flex-start',
                                        marginBottom: 10,
                                        backgroundColor: index % 2 === 0 ? '#f8f9fa' : '#fff',
                                        padding: 12,
                                        borderRadius: 8
                                    }}
                                >
                                    <View style={{
                                        width: 24,
                                        height: 24,
                                        borderRadius: 12,
                                        backgroundColor: '#1D3557',
                                        justifyContent: 'center',
                                        alignItems: 'center',
                                        marginRight: 12
                                    }}>
                                        <Text style={{ color: '#fff', fontSize: 12, fontWeight: '700' }}>{index + 1}</Text>
                                    </View>
                                    <Text style={{ flex: 1, fontSize: 14, color: '#333', lineHeight: 20 }}>
                                        {guideline}
                                    </Text>
                                </View>
                            ))}

                            {/* False Report Warning */}
                            <View style={{
                                backgroundColor: '#fee2e2',
                                padding: 14,
                                borderRadius: 10,
                                marginTop: 16,
                                marginBottom: 8,
                                borderLeftWidth: 4,
                                borderLeftColor: '#dc2626'
                            }}>
                                <View style={{ flexDirection: 'row', alignItems: 'flex-start' }}>
                                    <Ionicons name="warning" size={20} color="#dc2626" style={{ marginRight: 10, marginTop: 2 }} />
                                    <View style={{ flex: 1 }}>
                                        <Text style={{ color: '#991b1b', fontWeight: '700', marginBottom: 4, fontSize: 14 }}>
                                            Warning
                                        </Text>
                                        <Text style={{ color: '#991b1b', fontSize: 13, lineHeight: 18 }}>
                                            Filing false or misleading reports is punishable by law. Only report genuine incidents.
                                        </Text>
                                    </View>
                                </View>
                            </View>
                        </ScrollView>

                        {/* Close Button */}
                        <View style={{ paddingHorizontal: 20, paddingBottom: 20, paddingTop: 8 }}>
                            <TouchableOpacity
                                style={{
                                    backgroundColor: '#1D3557',
                                    paddingVertical: 14,
                                    borderRadius: 10,
                                    alignItems: 'center',
                                    shadowColor: '#1D3557',
                                    shadowOffset: { width: 0, height: 2 },
                                    shadowOpacity: 0.3,
                                    shadowRadius: 4,
                                    elevation: 3
                                }}
                                onPress={() => setShowGuidelinesModal(false)}
                            >
                                <Text style={{ color: '#fff', fontSize: 16, fontWeight: '600' }}>I Understand</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            <AnonymousGuidelinesModal
                visible={showAllGuidelinesModal}
                onClose={() => setShowAllGuidelinesModal(false)}
            />
        </View>
    );
}

const confirmStyles = StyleSheet.create({
    overlay: {
        flex: 1,
        backgroundColor: 'rgba(0, 0, 0, 0.6)',
        justifyContent: 'center',
        alignItems: 'center',
        padding: 20,
    },
    dialog: {
        backgroundColor: '#fff',
        borderRadius: 16,
        padding: 24,
        width: '100%',
        maxWidth: 440,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 8,
        elevation: 8,
    },
    iconContainer: {
        alignItems: 'center',
        marginBottom: 16,
    },
    title: {
        fontSize: 24,
        fontWeight: 'bold',
        color: '#1D3557',
        textAlign: 'center',
        marginBottom: 16,
    },
    message: {
        fontSize: 16,
        color: '#333',
        textAlign: 'center',
        lineHeight: 24,
        marginBottom: 12,
    },
    highlight: {
        fontWeight: '700',
        color: '#1D3557',
    },
    subMessage: {
        fontSize: 14,
        color: '#666',
        textAlign: 'center',
        lineHeight: 20,
        marginBottom: 20,
        fontStyle: 'italic',
    },
    question: {
        fontSize: 16,
        fontWeight: '600',
        color: '#1D3557',
        textAlign: 'center',
        marginBottom: 24,
    },
    detailsContainer: {
        borderWidth: 1,
        borderColor: '#e5e7eb',
        borderRadius: 12,
        backgroundColor: '#f9fafb',
        padding: 12,
        marginBottom: 16,
    },
    detailsTitle: {
        fontSize: 14,
        fontWeight: '800',
        color: '#111827',
        marginBottom: 8,
        textAlign: 'center',
    },
    detailsScroll: {
        maxHeight: 260,
    },
    detailsScrollContent: {
        paddingBottom: 4,
    },
    detailRow: {
        marginBottom: 10,
    },
    detailLabel: {
        fontSize: 12,
        fontWeight: '800',
        color: '#374151',
        marginBottom: 2,
        textTransform: 'uppercase',
    },
    detailValue: {
        fontSize: 14,
        color: '#111827',
        lineHeight: 20,
    },
    buttonContainer: {
        flexDirection: 'row',
        gap: 12,
    },
    button: {
        flex: 1,
        paddingVertical: 14,
        paddingHorizontal: 20,
        borderRadius: 8,
        alignItems: 'center',
        justifyContent: 'center',
        flexDirection: 'row',
    },
    cancelButton: {
        backgroundColor: '#f1f3f5',
        borderWidth: 1,
        borderColor: '#ddd',
    },
    cancelButtonText: {
        color: '#666',
        fontSize: 16,
        fontWeight: '600',
    },
    submitButton: {
        backgroundColor: '#1D3557',
        shadowColor: '#1D3557',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 4,
        elevation: 4,
    },
    submitButtonText: {
        color: '#fff',
        fontSize: 16,
        fontWeight: '700',
    },
});
