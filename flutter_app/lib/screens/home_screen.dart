import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../widgets/coupon_card.dart';
import '../widgets/category_chips.dart';
import '../widgets/search_input.dart';
import '../widgets/popular_coupons_carousel.dart';
import '../services/analytics_service.dart';
import '../models/coupon_model.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  String _selectedCategory = 'الكل';
  String _searchQuery = '';
  final ScrollController _scrollController = ScrollController();
  bool _isLoading = false;
  DocumentSnapshot? _lastDocument;
  final List<DocumentSnapshot> _coupons = [];
  
  @override
  void initState() {
    super.initState();
    _loadCoupons();
    _setupScrollListener();
    AnalyticsService.logScreenView('HomeScreen');
  }

  void _setupScrollListener() {
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= 
          _scrollController.position.maxScrollExtent - 200) {
        _loadMoreCoupons();
      }
    });
  }

  Future<void> _loadCoupons({bool refresh = false}) async {
    if (refresh) {
      setState(() {
        _isLoading = true;
        _coupons.clear();
        _lastDocument = null;
      });
    }

    try {
      Query query = _firestore
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .orderBy('usageCount', descending: true)
          .limit(20);

      if (_selectedCategory != 'الكل') {
        query = query.where('category', isEqualTo: _selectedCategory);
      }

      if (_searchQuery.isNotEmpty) {
        query = query
            .where('description', isGreaterThanOrEqualTo: _searchQuery)
            .where('description', isLessThanOrEqualTo: _searchQuery + '\uf8ff');
      }

      if (_lastDocument != null) {
        query = query.startAfterDocument(_lastDocument!);
      }

      QuerySnapshot snapshot = await query.get();
      
      if (mounted) {
        setState(() {
          _coupons.addAll(snapshot.docs);
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

  Future<void> _loadMoreCoupons() async {
    if (_isLoading || _lastDocument == null) return;
    
    setState(() {
      _isLoading = true;
    });
    
    await _loadCoupons();
  }

  void _onCategoryChanged(String category) {
    setState(() {
      _selectedCategory = category;
    });
    _loadCoupons(refresh: true);
    AnalyticsService.logCategoryView(category);
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query;
    });
    
    // Debounce search
    Future.delayed(const Duration(milliseconds: 500), () {
      if (mounted && _searchQuery == query) {
        _loadCoupons(refresh: true);
        AnalyticsService.logSearch(query, _coupons.length);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => _loadCoupons(refresh: true),
          child: CustomScrollView(
            controller: _scrollController,
            slivers: [
              // App Bar
              SliverAppBar(
                floating: true,
                snap: true,
                backgroundColor: Theme.of(context).colorScheme.primary,
                foregroundColor: Colors.white,
                title: Text(
                  'كوبونات',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                centerTitle: true,
                actions: [
                  IconButton(
                    icon: const Icon(Icons.category_outlined),
                    onPressed: () {
                      Navigator.pushNamed(context, '/categories');
                    },
                  ),
                  IconButton(
                    icon: const Icon(Icons.notifications_outlined),
                    onPressed: () {
                      // TODO: Navigate to notifications
                    },
                  ),
                ],
              ),

              // Search Bar
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: SearchInput(
                    onSearchChanged: _onSearchChanged,
                    onSearchSubmitted: (query) {
                      _onSearchChanged(query);
                    },
                  ),
                ),
              ),

              // Popular Coupons Carousel
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: 16.0),
                  child: PopularCouponsCarousel(),
                ),
              ),

              const SliverToBoxAdapter(
                child: SizedBox(height: 16),
              ),

              // Category Chips
              SliverToBoxAdapter(
                child: CategoryChips(
                  selectedCategory: _selectedCategory,
                  onCategoryChanged: _onCategoryChanged,
                ),
              ),

              const SliverToBoxAdapter(
                child: SizedBox(height: 16),
              ),

              // Section Title
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16.0),
                  child: Row(
                    children: [
                      Text(
                        _selectedCategory == 'الكل' ? 'جميع الكوبونات' : 'كوبونات $_selectedCategory',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        '${_coupons.length} كوبون',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SliverToBoxAdapter(
                child: SizedBox(height: 16),
              ),

              // Coupons Grid
              if (_coupons.isEmpty && !_isLoading)
                SliverToBoxAdapter(
                  child: Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.local_offer_outlined,
                          size: 64,
                          color: Colors.grey[400],
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'لا توجد كوبونات متاحة',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: Colors.grey[600],
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'حاول تغيير الفئة أو البحث',
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
                    if (index >= _coupons.length) {
                      return const SizedBox();
                    }
                    
                    final doc = _coupons[index];
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
                  childCount: _coupons.length,
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
      ),
    );
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }
}
