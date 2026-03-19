import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../widgets/coupon_card.dart';
import '../services/analytics_service.dart';
import '../models/coupon_model.dart';

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key});

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  String _userId = 'user123'; // TODO: Get from auth
  final ScrollController _scrollController = ScrollController();
  bool _isLoading = false;
  DocumentSnapshot? _lastDocument;
  final List<DocumentSnapshot> _favoriteCoupons = [];

  @override
  void initState() {
    super.initState();
    _loadFavoriteCoupons();
    _setupScrollListener();
    AnalyticsService.logScreenView('FavoritesScreen');
  }

  void _setupScrollListener() {
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= 
          _scrollController.position.maxScrollExtent - 200) {
        _loadMoreFavoriteCoupons();
      }
    });
  }

  Future<void> _loadFavoriteCoupons({bool refresh = false}) async {
    if (refresh) {
      setState(() {
        _isLoading = true;
        _favoriteCoupons.clear();
        _lastDocument = null;
      });
    }

    try {
      // Get user's favorite coupon IDs
      QuerySnapshot favoritesSnapshot = await _firestore
          .collection('users')
          .doc(_userId)
          .collection('favorites')
          .orderBy('addedAt', descending: true)
          .limit(20)
          .get();

      if (favoritesSnapshot.docs.isEmpty) {
        setState(() {
          _isLoading = false;
        });
        return;
      }

      // Get coupon details
      List<String> couponIds = favoritesSnapshot.docs
          .map((doc) => doc['couponId'] as String)
          .toList();

      Query query = _firestore
          .collection('coupons')
          .where(FieldPath.documentId, whereIn: couponIds)
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now());

      if (_lastDocument != null) {
        query = query.startAfterDocument(_lastDocument!);
      }

      QuerySnapshot snapshot = await query.get();
      
      if (mounted) {
        setState(() {
          _favoriteCoupons.addAll(snapshot.docs);
          _lastDocument = snapshot.docs.isNotEmpty ? snapshot.docs.last : null;
          _isLoading = false;
        });
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

  Future<void> _loadMoreFavoriteCoupons() async {
    if (_isLoading || _lastDocument == null) return;
    
    setState(() {
      _isLoading = true;
    });
    
    await _loadFavoriteCoupons();
  }

  Future<void> _toggleFavorite(String couponId) async {
    try {
      DocumentReference favoriteRef = _firestore
          .collection('users')
          .doc(_userId)
          .collection('favorites')
          .doc(couponId);

      DocumentSnapshot favoriteDoc = await favoriteRef.get();

      if (favoriteDoc.exists) {
        // Remove from favorites
        await favoriteRef.delete();
        AnalyticsService.logEvent('coupon_unfavorited', parameters: {
          'coupon_id': couponId,
        });
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('تم إزالة الكوبون من المفضلة'),
              backgroundColor: Colors.orange,
            ),
          );
        }
      } else {
        // Add to favorites
        await favoriteRef.set({
          'couponId': couponId,
          'addedAt': Timestamp.now(),
        });
        AnalyticsService.logEvent('coupon_favorited', parameters: {
          'coupon_id': couponId,
        });
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('تم إضافة الكوبون إلى المفضلة'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
      
      _loadFavoriteCoupons(refresh: true);
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('المفضلة'),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
      ),
      body: RefreshIndicator(
        onRefresh: () => _loadFavoriteCoupons(refresh: true),
        child: CustomScrollView(
          controller: _scrollController,
          slivers: [
            // Section Title
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Row(
                  children: [
                    Text(
                      'كوبوناتي المفضلة',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '${_favoriteCoupons.length} كوبون',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Coupons Grid
            if (_favoriteCoupons.isEmpty && !_isLoading)
              SliverToBoxAdapter(
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.favorite_border,
                        size: 64,
                        color: Colors.grey[400],
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'لا توجد كوبونات مفضلة',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: Colors.grey[600],
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'أضف كوبونات إلى المفضلة لتظهر هنا',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Colors.grey[500],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

            SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.7,
                crossAxisSpacing: 8,
                mainAxisSpacing: 8,
              ),
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  if (index >= _favoriteCoupons.length) {
                    return const SizedBox();
                  }
                  
                  final doc = _favoriteCoupons[index];
                  final coupon = Coupon.fromFirestore(doc);
                  
                  return CouponCard(
                    coupon: coupon,
                    onTap: () {
                      Navigator.pushNamed(
                        context,
                        '/coupon',
                        arguments: {
                          'couponId': doc.id,
                          'coupon': coupon,
                        },
                      );
                    },
                  );
                },
                childCount: _favoriteCoupons.length,
              ),
            ),

            // Loading Indicator
            if (_isLoading)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(16.0),
                  child: Center(
                    child: CircularProgressIndicator(),
                  ),
                ),
              ),

            // Bottom Padding
            const SliverToBoxAdapter(
              child: SizedBox(height: 100),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }
}
