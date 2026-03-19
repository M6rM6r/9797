import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:share_plus/share_plus.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../models/coupon_model.dart';
import '../services/analytics_service.dart';

class CouponDetailScreen extends StatefulWidget {
  final String? couponId;
  final Coupon? coupon;

  const CouponDetailScreen({
    super.key,
    this.couponId,
    this.coupon,
  }) : assert(couponId != null || coupon != null);

  @override
  State<CouponDetailScreen> createState() => _CouponDetailScreenState();
}

class _CouponDetailScreenState extends State<CouponDetailScreen> {
  Coupon? _coupon;
  bool _isLoading = true;
  bool _isCopied = false;

  @override
  void initState() {
    super.initState();
    _loadCoupon();
    AnalyticsService.logScreenView('CouponDetailScreen');
  }

  Future<void> _loadCoupon() async {
    if (widget.coupon != null) {
      setState(() {
        _coupon = widget.coupon;
        _isLoading = false;
      });
      return;
    }

    try {
      DocumentSnapshot doc = await FirebaseFirestore.instance
          .collection('coupons')
          .doc(widget.couponId)
          .get();

      if (doc.exists) {
        final coupon = Coupon.fromFirestore(doc);
        
        // Load store information
        await _loadStoreInfo(coupon);
        
        setState(() {
          _coupon = coupon;
          _isLoading = false;
        });

        // Log view
        await AnalyticsService.logViewCoupon(
          coupon.id ?? '',
          coupon.storeName ?? 'Unknown',
          coupon.category,
        );
      } else {
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('الكوبون غير موجود'),
              backgroundColor: Colors.red,
            ),
          );
          Navigator.pop(context);
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('حدث خطأ: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _loadStoreInfo(Coupon coupon) async {
    try {
      DocumentSnapshot storeDoc = await FirebaseFirestore.instance
          .collection('stores')
          .doc(coupon.storeId)
          .get();

      if (storeDoc.exists) {
        final storeData = storeDoc.data() as Map<String, dynamic>;
        _coupon = coupon.copyWith(
          storeName: storeData['name'] ?? '',
          storeLogo: storeData['logoUrl'] ?? '',
          storeCashback: storeData['cashbackPercent']?.toString() ?? '',
        );
      }
    } catch (e) {
      // Store info loading failed, continue with coupon data
    }
  }

  Future<void> _copyCode() async {
    if (_coupon == null) return;

    try {
      await Clipboard.setData(ClipboardData(text: _coupon!.code));

      setState(() {
        _isCopied = true;
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.white),
                const SizedBox(width: 8),
                Text('تم نسخ الكود: ${_coupon!.code}'),
              ],
            ),
            backgroundColor: Colors.green,
            duration: const Duration(seconds: 2),
          ),
        );
      }

      // Reset copy state after 2 seconds
      Future.delayed(const Duration(seconds: 2), () {
        if (mounted) {
          setState(() {
            _isCopied = false;
          });
        }
      });

      // Log analytics
      await AnalyticsService.logCouponCopy(
        _coupon!.id ?? '',
        _coupon!.storeName ?? 'Unknown',
        _coupon!.discountText,
      );

      // Increment usage count
      await _incrementUsageCount();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('فشل نسخ الكود: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _incrementUsageCount() async {
    if (_coupon == null || _coupon!.id == null) return;

    try {
      await FirebaseFirestore.instance
          .collection('coupons')
          .doc(_coupon!.id)
          .update({
        'usageCount': FieldValue.increment(1),
        'updatedAt': Timestamp.now(),
      });
    } catch (e) {
      // Silently fail for analytics
    }
  }

  Future<void> _openStoreLink() async {
    if (_coupon == null || _coupon!.affiliateLink.isEmpty) return;

    try {
      final uri = Uri.parse(_coupon!.affiliateLink);
      if (await canLaunchUrl(uri)) {
        await launchUrl(
          uri,
          mode: LaunchMode.externalApplication,
        );

        // Log analytics
        await AnalyticsService.logStoreRedirect(
          _coupon!.storeName ?? 'Unknown',
          _coupon!.id ?? '',
        );
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('لا يمكن فتح الرابط'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('حدث خطأ: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _shareCoupon() async {
    if (_coupon == null) return;

    try {
      final shareText = '''
كود خصم ${_coupon!.discountPercent}% على ${_coupon!.storeName ?? 'المتجر'}

الكود: ${_coupon!.code}
الوصف: ${_coupon!.description}

احصل على الكوبون من تطبيق كوبونات!
      ''';

      await Share.share(shareText);

      // Log analytics
      await AnalyticsService.logCouponShare(
        _coupon!.id ?? '',
        _coupon!.storeName ?? 'Unknown',
        'share_dialog',
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('فشل المشاركة: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(),
            )
          : _coupon == null
              ? const Center(
                  child: Text('الكوبون غير موجود'),
                )
              : _buildContent(),
    );
  }

  Widget _buildContent() {
    return CustomScrollView(
      slivers: [
        // App Bar with Store Logo
        SliverAppBar(
          expandedHeight: 200,
          floating: false,
          pinned: true,
          backgroundColor: Theme.of(context).colorScheme.primary,
          flexibleSpace: FlexibleSpaceBar(
            background: _coupon!.storeLogo != null
                ? CachedNetworkImage(
                    imageUrl: _coupon!.storeLogo!,
                    fit: BoxFit.contain,
                    placeholder: (context, url) => Container(
                      color: Colors.white,
                      child: const Center(
                        child: CircularProgressIndicator(),
                      ),
                    ),
                    errorWidget: (context, url, error) => Container(
                      color: Colors.white,
                      child: Icon(
                        Icons.store,
                        size: 64,
                        color: Colors.grey[400],
                      ),
                    ),
                  )
                : Container(
                    color: Colors.white,
                    child: Icon(
                      Icons.store,
                      size: 64,
                      color: Colors.grey[400],
                    ),
                  ),
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.share),
              onPressed: _shareCoupon,
            ),
          ],
        ),

        // Content
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                // Store Name and Verified Badge
                Row(
                  children: [
                    if (_coupon!.isVerified)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.green,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.verified,
                              color: Colors.white,
                              size: 16,
                            ),
                            SizedBox(width: 4),
                            Text(
                              'موثق',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    const Spacer(),
                    Text(
                      _coupon!.storeName ?? 'المتجر',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 16),

                // Discount Badge
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primary,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Text(
                        'خصم',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: Colors.white,
                        ),
                      ),
                      Text(
                        '${_coupon!.discountPercent}%',
                        style: Theme.of(context).textTheme.headlineLarge?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                // Coupon Code
                Container(
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: _isCopied ? Colors.green : Colors.grey[300]!,
                      width: 2,
                    ),
                    borderRadius: BorderRadius.circular(12),
                    color: _isCopied ? Colors.green.withOpacity(0.1) : Colors.white,
                  ),
                  child: Column(
                    children: [
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.grey[50],
                          borderRadius: const BorderRadius.only(
                            topRight: Radius.circular(10),
                            topLeft: Radius.circular(10),
                          ),
                        ),
                        child: Text(
                          'كود الخصم',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: Colors.grey[600],
                          ),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(20),
                        child: Text(
                          _coupon!.code,
                          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            fontFamily: 'monospace',
                            color: _isCopied ? Colors.green : null,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                      InkWell(
                        onTap: _copyCode,
                        child: Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: _isCopied ? Colors.green : Theme.of(context).colorScheme.primary,
                            borderRadius: const BorderRadius.only(
                              bottomRight: Radius.circular(10),
                              bottomLeft: Radius.circular(10),
                            ),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                _isCopied ? Icons.check : Icons.copy,
                                color: Colors.white,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                _isCopied ? 'تم النسخ!' : 'نسخ الكود',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                // Description
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.grey[50],
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        'تفاصيل العرض',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _coupon!.description,
                        style: Theme.of(context).textTheme.bodyMedium,
                        textAlign: TextAlign.right,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                // Stats
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.blue.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              Icons.people,
                              color: Colors.blue[600],
                              size: 24,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${_coupon!.usageCount}',
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.bold,
                                color: Colors.blue[600],
                              ),
                            ),
                            Text(
                              'مستخدم',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: Colors.blue[600],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: _coupon!.isExpiringSoon
                              ? Colors.red.withOpacity(0.1)
                              : Colors.green.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              _coupon!.isExpiringSoon ? Icons.warning : Icons.schedule,
                              color: _coupon!.isExpiringSoon ? Colors.red : Colors.green,
                              size: 24,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _coupon!.expiryText,
                              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.bold,
                                color: _coupon!.isExpiringSoon ? Colors.red : Colors.green,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),

                // Cashback Info
                if (_coupon!.storeCashback != null && _coupon!.storeCashback!.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 16),
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.amber.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: Colors.amber.withOpacity(0.3),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.monetization_on,
                            color: Colors.amber[600],
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'كاش باك متوقع: ${_coupon!.storeCashback}%',
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: Colors.amber[700],
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                const SizedBox(height: 24),

                // Action Buttons
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: _copyCode,
                        icon: const Icon(Icons.copy),
                        label: const Text('نسخ الكود'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _isCopied ? Colors.green : null,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _openStoreLink,
                        icon: const Icon(Icons.shopping_cart),
                        label: const Text('اذهب للمتجر'),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 32),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
