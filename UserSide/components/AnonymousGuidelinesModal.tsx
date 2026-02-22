import React, { useState } from 'react';
import { View, Text, ScrollView, TouchableOpacity, Platform, Modal, LayoutAnimation, UIManager } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

if (Platform.OS === 'android' && UIManager.setLayoutAnimationEnabledExperimental) {
    UIManager.setLayoutAnimationEnabledExperimental(true);
}

const COLORS = {
    primary: '#1D3557',
    accent: '#E63946',
    white: '#ffffff',
    background: '#f5f7fa',
    cardBg: '#ffffff',
    textPrimary: '#1e293b',
    textSecondary: '#475569',
    textMuted: '#64748b',
    border: '#e5e7eb',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    info: '#3b82f6',
};

// Accordion Component
const AccordionItem = ({
    title,
    children,
    icon,
    iconColor = COLORS.primary,
    isWarning = false,
    defaultExpanded = false
}: {
    title: string;
    children: React.ReactNode;
    icon?: string;
    iconColor?: string;
    isWarning?: boolean;
    defaultExpanded?: boolean;
}) => {
    const [expanded, setExpanded] = useState(defaultExpanded);

    const toggleExpand = () => {
        LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
        setExpanded(!expanded);
    };

    return (
        <View style={{
            backgroundColor: COLORS.cardBg,
            borderRadius: 12,
            marginBottom: 12,
            overflow: 'hidden',
            borderLeftWidth: isWarning ? 4 : 0,
            borderLeftColor: COLORS.danger,
            shadowColor: '#000',
            shadowOffset: { width: 0, height: 1 },
            shadowOpacity: 0.05,
            shadowRadius: 4,
            elevation: 2,
        }}>
            <TouchableOpacity
                onPress={toggleExpand}
                style={{
                    flexDirection: 'row',
                    alignItems: 'center',
                    padding: 16,
                    backgroundColor: expanded ? '#f8fafc' : COLORS.cardBg,
                }}
            >
                {icon && (
                    <View style={{
                        width: 36,
                        height: 36,
                        borderRadius: 18,
                        backgroundColor: isWarning ? '#fef2f2' : '#eff6ff',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginRight: 12,
                    }}>
                        <Ionicons name={icon as any} size={20} color={iconColor} />
                    </View>
                )}
                <Text style={{
                    flex: 1,
                    fontSize: 15,
                    fontWeight: '600',
                    color: COLORS.textPrimary
                }}>
                    {title}
                </Text>
                <Ionicons
                    name={expanded ? 'chevron-up' : 'chevron-down'}
                    size={20}
                    color={COLORS.textMuted}
                />
            </TouchableOpacity>
            {expanded && (
                <View style={{ padding: 16, paddingTop: 0 }}>
                    {children}
                </View>
            )}
        </View>
    );
};

// Crime Type Accordion
const CrimeTypeAccordion = ({
    title,
    definition,
    examples,
    icon,
    iconColor
}: {
    title: string;
    definition: string;
    examples: string[];
    icon: string;
    iconColor: string;
}) => {
    const [expanded, setExpanded] = useState(false);

    const toggleExpand = () => {
        LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
        setExpanded(!expanded);
    };

    return (
        <View style={{
            backgroundColor: '#f8fafc',
            borderRadius: 10,
            marginBottom: 10,
            overflow: 'hidden',
            borderWidth: 1,
            borderColor: expanded ? COLORS.primary : '#e2e8f0',
        }}>
            <TouchableOpacity
                onPress={toggleExpand}
                style={{
                    flexDirection: 'row',
                    alignItems: 'center',
                    padding: 14,
                }}
            >
                <View style={{
                    width: 32,
                    height: 32,
                    borderRadius: 16,
                    backgroundColor: iconColor + '20',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginRight: 12,
                }}>
                    <Ionicons name={icon as any} size={16} color={iconColor} />
                </View>
                <Text style={{
                    flex: 1,
                    fontSize: 14,
                    fontWeight: '600',
                    color: COLORS.textPrimary
                }}>
                    {title}
                </Text>
                <Ionicons
                    name={expanded ? 'chevron-up' : 'chevron-down'}
                    size={18}
                    color={COLORS.textMuted}
                />
            </TouchableOpacity>
            {expanded && (
                <View style={{
                    padding: 14,
                    paddingTop: 0,
                    borderTopWidth: 1,
                    borderTopColor: '#e2e8f0',
                }}>
                    <Text style={{
                        fontSize: 13,
                        color: COLORS.textSecondary,
                        lineHeight: 20,
                        marginBottom: 10,
                    }}>
                        <Text style={{ fontWeight: '600' }}>Definition: </Text>
                        {definition}
                    </Text>
                    <Text style={{
                        fontSize: 13,
                        fontWeight: '600',
                        color: COLORS.textPrimary,
                        marginBottom: 6,
                    }}>
                        Examples:
                    </Text>
                    {examples.map((example, idx) => (
                        <View key={idx} style={{ flexDirection: 'row', marginBottom: 4, paddingLeft: 8 }}>
                            <Text style={{ color: COLORS.textMuted, marginRight: 8 }}>•</Text>
                            <Text style={{ fontSize: 13, color: COLORS.textSecondary, flex: 1 }}>{example}</Text>
                        </View>
                    ))}
                </View>
            )}
        </View>
    );
};

const crimeTypes = [
    {
        title: 'Theft',
        icon: 'hand-left',
        iconColor: '#f59e0b',
        definition: 'The unlawful taking of someone else\'s property without their consent and with the intent to permanently deprive them of it. Unlike robbery, theft does not involve force or intimidation.',
        examples: [
            'Pickpocketing in public areas',
            'Shoplifting from stores',
            'Stealing unattended belongings',
            'Vehicle break-ins',
        ],
    },
    {
        title: 'Robbery',
        icon: 'skull',
        iconColor: '#ef4444',
        definition: 'The crime of taking or attempting to take property from a person by force, threat of force, or by putting the victim in fear. Robbery involves direct confrontation with the victim.',
        examples: [
            'Holdup at gunpoint or knifepoint',
            'Forcibly taking a bag or phone',
            'Carjacking',
            'Home invasion robbery',
        ],
    },
    {
        title: 'Physical Injury / Assault',
        icon: 'bandage',
        iconColor: '#dc2626',
        definition: 'The intentional act of causing physical harm to another person. This includes any unlawful touching or application of force that results in bodily injury.',
        examples: [
            'Punching, kicking, or hitting someone',
            'Causing injuries during a fight',
            'Physical attacks with weapons',
            'Injuries from road rage incidents',
        ],
    },
    {
        title: 'Cybercrime',
        icon: 'globe',
        iconColor: '#6366f1',
        definition: 'Criminal activities carried out using computers, networks, or the internet. This includes various forms of online fraud, hacking, and digital harassment.',
        examples: [
            'Online scams and phishing',
            'Hacking and unauthorized access',
            'Identity theft',
            'Cyberbullying and online harassment',
            'Online fraud and money schemes',
        ],
    },
    {
        title: 'Domestic Violence',
        icon: 'home',
        iconColor: '#8b5cf6',
        definition: 'A pattern of abusive behavior in any relationship used to gain or maintain power and control over an intimate partner or family member. Includes physical, emotional, sexual, or economic abuse.',
        examples: [
            'Physical abuse between partners',
            'Emotional or psychological abuse',
            'Child abuse within the family',
            'Elder abuse by family members',
        ],
    },
    {
        title: 'Missing Person',
        icon: 'search',
        iconColor: '#0ea5e9',
        definition: 'A person whose whereabouts are unknown and who may be at risk. This includes voluntary disappearances, runaways, abductions, and lost individuals.',
        examples: [
            'Missing children or minors',
            'Elderly with dementia who wandered off',
            'Persons who failed to return home',
            'Suspected kidnapping or abduction',
        ],
    },
    {
        title: 'Drug-Related Incidents',
        icon: 'medical',
        iconColor: '#14b8a6',
        definition: 'Activities related to illegal drugs including possession, use, sale, or distribution of controlled substances.',
        examples: [
            'Drug pushing or selling',
            'Drug dens or drug use areas',
            'Possession of illegal substances',
            'Drug-influenced behavior',
        ],
    },
    {
        title: 'Vandalism / Property Damage',
        icon: 'construct',
        iconColor: '#f97316',
        definition: 'The intentional destruction, defacement, or damage of public or private property without the owner\'s consent.',
        examples: [
            'Graffiti on public property',
            'Breaking windows or fixtures',
            'Destroying street signs',
            'Damage to vehicles',
        ],
    },
    {
        title: 'Disturbance / Public Disorder',
        icon: 'megaphone',
        iconColor: '#eab308',
        definition: 'Activities that disrupt public peace and order, causing annoyance or alarm to the community.',
        examples: [
            'Loud parties or excessive noise',
            'Street brawls or fights',
            'Public intoxication',
            'Disorderly conduct',
        ],
    },
    {
        title: 'Suspicious Activity',
        icon: 'eye',
        iconColor: '#64748b',
        definition: 'Behavior or circumstances that appear unusual and may indicate potential criminal activity or security threat.',
        examples: [
            'Unfamiliar persons loitering',
            'Suspicious vehicles casing an area',
            'Unusual activity at odd hours',
            'Unattended packages or bags',
        ],
    },
    {
        title: 'Other Incidents',
        icon: 'alert-circle',
        iconColor: '#94a3b8',
        definition: 'Any other incident not covered by the categories above that requires police attention or response.',
        examples: [
            'Traffic accidents',
            'Fire or emergency situations',
            'Animal-related incidents',
            'Community concerns',
        ],
    },
];

interface AnonymousGuidelinesModalProps {
    visible: boolean;
    onClose: () => void;
}

const AnonymousGuidelinesModal: React.FC<AnonymousGuidelinesModalProps> = ({ visible, onClose }) => {
    return (
        <Modal
            visible={visible}
            transparent={true}
            animationType="slide"
            onRequestClose={onClose}
        >
            <View style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center' }}>
                <View style={{
                    backgroundColor: '#f5f7fa',
                    borderRadius: 16,
                    margin: 16,
                    padding: 0,
                    maxHeight: '85%',
                    overflow: 'hidden',
                    shadowColor: '#000',
                    shadowOffset: { width: 0, height: 10 },
                    shadowOpacity: 0.25,
                    shadowRadius: 10,
                    elevation: 5
                }}>
                    {/* Header */}
                    <View style={{
                        backgroundColor: COLORS.white,
                        borderBottomWidth: 1,
                        borderBottomColor: COLORS.border,
                        paddingHorizontal: 20,
                        paddingVertical: 16,
                        flexDirection: 'row',
                        justifyContent: 'space-between',
                        alignItems: 'center'
                    }}>
                        <View style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }}>
                            <View style={{
                                width: 36,
                                height: 36,
                                borderRadius: 18,
                                backgroundColor: '#eff6ff',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginRight: 12,
                            }}>
                                <Ionicons name="shield-checkmark" size={20} color={COLORS.primary} />
                            </View>
                            <Text style={{ fontSize: 18, fontWeight: '700', color: COLORS.textPrimary }}>
                                Reporting Guidelines
                            </Text>
                        </View>
                        <TouchableOpacity onPress={onClose} style={{ padding: 4, backgroundColor: '#f1f5f9', borderRadius: 20 }}>
                            <Ionicons name="close" size={24} color={COLORS.textMuted} />
                        </TouchableOpacity>
                    </View>

                    <ScrollView style={{ paddingHorizontal: 20, paddingVertical: 16 }}>
                        {/* Anonymous Warning Banner */}
                        <View style={{
                            backgroundColor: '#fff3cd',
                            borderRadius: 12,
                            padding: 16,
                            marginBottom: 20,
                            flexDirection: 'row',
                            alignItems: 'flex-start',
                            borderWidth: 1,
                            borderColor: '#ffc107',
                        }}>
                            <Ionicons name="eye-off" size={24} color="#856404" style={{ marginRight: 12, marginTop: 2 }} />
                            <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 14, fontWeight: '700', color: '#856404', marginBottom: 4 }}>
                                    Anonymous Reporting
                                </Text>
                                <Text style={{ fontSize: 13, color: '#856404', lineHeight: 20 }}>
                                    You will NOT receive updates about your report. Ensure all details are accurate as police cannot contact you for follow-ups.
                                </Text>
                            </View>
                        </View>

                        {/* General Guidelines */}
                        <AccordionItem
                            title="1. General Reporting Guidelines"
                            icon="document-text"
                            iconColor={COLORS.info}
                            defaultExpanded={true}
                        >
                            <View style={{ marginTop: 8 }}>
                                {[
                                    { icon: 'checkmark-circle', color: COLORS.success, text: 'Provide accurate and truthful information' },
                                    { icon: 'location', color: COLORS.info, text: 'Include precise location details when possible' },
                                    { icon: 'time', color: COLORS.warning, text: 'Report incidents as soon as possible' },
                                    { icon: 'camera', color: COLORS.primary, text: 'Attach clear photos or evidence if available' },
                                ].map((item, index) => (
                                    <View key={index} style={{ flexDirection: 'row', alignItems: 'flex-start', marginBottom: 12 }}>
                                        <Ionicons name={item.icon as any} size={18} color={item.color} style={{ marginRight: 12, marginTop: 2 }} />
                                        <Text style={{ fontSize: 14, color: COLORS.textSecondary, flex: 1, lineHeight: 20 }}>{item.text}</Text>
                                    </View>
                                ))}
                            </View>
                        </AccordionItem>

                        {/* Prohibited Content */}
                        <AccordionItem
                            title="2. Prohibited Content (IMPORTANT)"
                            icon="warning"
                            iconColor={COLORS.danger}
                            isWarning={true}
                            defaultExpanded={false}
                        >
                            <View style={{ marginTop: 8 }}>
                                <View style={{
                                    backgroundColor: '#fef2f2',
                                    borderRadius: 10,
                                    padding: 14,
                                    marginBottom: 16,
                                    borderWidth: 1,
                                    borderColor: '#fecaca',
                                }}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 8 }}>
                                        <Ionicons name="alert-circle" size={20} color={COLORS.danger} />
                                        <Text style={{ fontSize: 14, fontWeight: '700', color: '#b91c1c', marginLeft: 8 }}>
                                            STRICTLY PROHIBITED
                                        </Text>
                                    </View>
                                    <Text style={{ fontSize: 13, color: '#991b1b', lineHeight: 20 }}>
                                        Filing false reports or uploading prohibited content (sexual, voyeuristic, hateful) is punishable by law and may be investigated.
                                    </Text>
                                </View>
                            </View>
                        </AccordionItem>

                        {/* What to Report */}
                        <AccordionItem
                            title="3. What You Should Report"
                            icon="checkmark-done-circle"
                            iconColor={COLORS.success}
                        >
                            <View style={{ marginTop: 8 }}>
                                {[
                                    { icon: 'alert-circle', color: '#f59e0b', text: 'Crimes in progress or recently occurred' },
                                    { icon: 'medical', color: '#ef4444', text: 'Emergency situations requiring immediate response' },
                                    { icon: 'people', color: '#8b5cf6', text: 'Suspicious activities in your community' },
                                    { icon: 'car', color: '#0ea5e9', text: 'Traffic accidents or road hazards' },
                                ].map((item, index) => (
                                    <View key={index} style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 12 }}>
                                        <View style={{
                                            width: 28,
                                            height: 28,
                                            borderRadius: 14,
                                            backgroundColor: item.color + '20',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            marginRight: 12,
                                        }}>
                                            <Ionicons name={item.icon as any} size={14} color={item.color} />
                                        </View>
                                        <Text style={{ fontSize: 14, color: COLORS.textSecondary, flex: 1 }}>{item.text}</Text>
                                    </View>
                                ))}
                            </View>
                        </AccordionItem>

                        {/* Crime Types / Categories */}
                        <AccordionItem
                            title="4. Crime Type Definitions"
                            icon="list"
                            iconColor={COLORS.primary}
                            defaultExpanded={false}
                        >
                            <View style={{ marginTop: 8 }}>
                                <Text style={{ fontSize: 13, color: COLORS.textMuted, marginBottom: 16, lineHeight: 18 }}>
                                    Tap on each category to see its definition and examples.
                                </Text>
                                {crimeTypes.map((crime, index) => (
                                    <CrimeTypeAccordion
                                        key={index}
                                        title={crime.title}
                                        definition={crime.definition}
                                        examples={crime.examples}
                                        icon={crime.icon}
                                        iconColor={crime.iconColor}
                                    />
                                ))}
                            </View>
                        </AccordionItem>

                        {/* Bottom Spacer */}
                        <View style={{ height: 20 }} />
                    </ScrollView>

                    {/* Bottom Action */}
                    <View style={{
                        paddingHorizontal: 20,
                        paddingVertical: 16,
                        backgroundColor: COLORS.white,
                        borderTopWidth: 1,
                        borderTopColor: COLORS.border,
                    }}>
                        <TouchableOpacity
                            onPress={onClose}
                            style={{
                                backgroundColor: COLORS.primary,
                                paddingVertical: 14,
                                borderRadius: 10,
                                alignItems: 'center',
                                shadowColor: COLORS.primary,
                                shadowOffset: { width: 0, height: 2 },
                                shadowOpacity: 0.3,
                                shadowRadius: 4,
                                elevation: 3
                            }}
                        >
                            <Text style={{ color: COLORS.white, fontSize: 16, fontWeight: '700' }}>I Understand & Continue</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </View>
        </Modal>
    );
};

export default AnonymousGuidelinesModal;
