import 'package:cloud_firestore/cloud_firestore.dart';

class Coupon {
  final String? id;
  final String code;
  final String storeId;
  final int discountPercent;
  final String description;
  final Timestamp expiresAt;
  final int usageCount;
  final String category;
  final bool isVerified;
  final String affiliateLink;
  final bool isActive;
  final Timestamp createdAt;
  final Timestamp updatedAt;
  final String? storeName;
  final String? storeLogo;
  final String? storeCashback;
  final String? imageUrl;
  final double? originalPrice;
  final double? discountedPrice;
  final double? rating;
  final int socialShares;
  final double? clickThroughRate;
  final double? conversionRate;
  final String? storeUrl;

  Coupon({
    this.id,
    required this.code,
    required this.storeId,
    required this.discountPercent,
    required this.description,
    required this.expiresAt,
    required this.usageCount,
    required this.category,
    required this.isVerified,
    required this.affiliateLink,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    this.storeName,
    this.storeLogo,
    this.storeCashback,
    this.imageUrl,
    this.originalPrice,
    this.discountedPrice,
    this.rating,
    this.socialShares = 0,
    this.clickThroughRate,
    this.conversionRate,
    this.storeUrl,
  });

  factory Coupon.fromFirestore(DocumentSnapshot doc) {
    final data = doc.data() as Map<String, dynamic>;
    
    return Coupon(
      id: doc.id,
      code: data['code'] ?? '',
      storeId: data['storeId'] ?? '',
      discountPercent: (data['discountPercent'] ?? 0).toInt(),
      description: data['description'] ?? '',
      expiresAt: data['expiresAt'] ?? Timestamp.now(),
      usageCount: (data['usageCount'] ?? 0).toInt(),
      category: data['category'] ?? '',
      isVerified: data['isVerified'] ?? false,
      affiliateLink: data['affiliateLink'] ?? '',
      isActive: data['isActive'] ?? true,
      createdAt: data['createdAt'] ?? Timestamp.now(),
      updatedAt: data['updatedAt'] ?? Timestamp.now(),
      storeName: data['storeName'],
      storeLogo: data['storeLogo'],
      storeCashback: data['storeCashback'],
      imageUrl: data['imageUrl'],
      originalPrice: (data['originalPrice'] ?? 0).toDouble(),
      discountedPrice: (data['discountedPrice'] ?? 0).toDouble(),
      rating: (data['rating'] ?? 0).toDouble(),
      socialShares: (data['socialShares'] ?? 0).toInt(),
      clickThroughRate: (data['clickThroughRate'] ?? 0).toDouble(),
      conversionRate: (data['conversionRate'] ?? 0).toDouble(),
      storeUrl: data['storeUrl'],
    );
  }

  Map<String, dynamic> toFirestore() {
    return {
      'code': code,
      'storeId': storeId,
      'discountPercent': discountPercent,
      'description': description,
      'expiresAt': expiresAt,
      'usageCount': usageCount,
      'category': category,
      'isVerified': isVerified,
      'affiliateLink': affiliateLink,
      'isActive': isActive,
      'createdAt': createdAt,
      'updatedAt': updatedAt,
      if (storeName != null) 'storeName': storeName,
      if (storeLogo != null) 'storeLogo': storeLogo,
      if (storeCashback != null) 'storeCashback': storeCashback,
      if (imageUrl != null) 'imageUrl': imageUrl,
      'originalPrice': originalPrice,
      'discountedPrice': discountedPrice,
      'rating': rating,
      'socialShares': socialShares,
      'clickThroughRate': clickThroughRate,
      'conversionRate': conversionRate,
      if (storeUrl != null) 'storeUrl': storeUrl,
    };
  }

  Coupon copyWith({
    String? id,
    String? code,
    String? storeId,
    int? discountPercent,
    String? description,
    Timestamp? expiresAt,
    int? usageCount,
    String? category,
    bool? isVerified,
    String? affiliateLink,
    bool? isActive,
    Timestamp? createdAt,
    Timestamp? updatedAt,
    String? storeName,
    String? storeLogo,
    String? storeCashback,
    String? imageUrl,
    double? originalPrice,
    double? discountedPrice,
    double? rating,
    int? socialShares,
    double? clickThroughRate,
    double? conversionRate,
    String? storeUrl,
  }) {
    return Coupon(
      id: id ?? this.id,
      code: code ?? this.code,
      storeId: storeId ?? this.storeId,
      discountPercent: discountPercent ?? this.discountPercent,
      description: description ?? this.description,
      expiresAt: expiresAt ?? this.expiresAt,
      usageCount: usageCount ?? this.usageCount,
      category: category ?? this.category,
      isVerified: isVerified ?? this.isVerified,
      affiliateLink: affiliateLink ?? this.affiliateLink,
      isActive: isActive ?? this.isActive,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      storeName: storeName ?? this.storeName,
      storeLogo: storeLogo ?? this.storeLogo,
      storeCashback: storeCashback ?? this.storeCashback,
      imageUrl: imageUrl ?? this.imageUrl,
      originalPrice: originalPrice ?? this.originalPrice,
      discountedPrice: discountedPrice ?? this.discountedPrice,
      rating: rating ?? this.rating,
      socialShares: socialShares ?? this.socialShares,
      clickThroughRate: clickThroughRate ?? this.clickThroughRate,
      conversionRate: conversionRate ?? this.conversionRate,
      storeUrl: storeUrl ?? this.storeUrl,
    );
  }

  // Helper methods
  bool get isExpired {
    return expiresAt.toDate().isBefore(DateTime.now());
  }

  bool get isExpiringSoon {
    final now = DateTime.now();
    final expiryDate = expiresAt.toDate();
    final difference = expiryDate.difference(now);
    return difference.inDays <= 3 && !isExpired;
  }

  String get discountText {
    return '$discountPercent%';
  }

  String get expiryText {
    final now = DateTime.now();
    final expiryDate = expiresAt.toDate();
    final difference = expiryDate.difference(now);
    
    if (isExpired) {
      return 'منتهي الصلاحية';
    } else if (difference.inDays > 0) {
      return 'ينتهي خلال ${difference.inDays} يوم';
    } else if (difference.inHours > 0) {
      return 'ينتهي خلال ${difference.inHours} ساعة';
    } else {
      return 'ينتهي قريباً';
    }
  }

  String get categoryDisplayName {
    switch (category) {
      case 'fashion':
        return 'تسوق وأزياء';
      case 'electronics':
        return 'إلكترونيات';
      case 'food':
        return 'طعام ومطاعم';
      case 'travel':
        return 'سفر وفنادق';
      default:
        return category;
    }
  }

  @override
  String toString() {
    return 'Coupon(id: $id, code: $code, storeId: $storeId, discount: $discountPercent%)';
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Coupon && other.id == id;
  }

  @override
  int get hashCode => id.hashCode;
}
