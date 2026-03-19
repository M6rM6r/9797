import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../widgets/coupon_card.dart';
import '../widgets/category_chips.dart';
import '../widgets/search_bar.dart';
import '../widgets/filter_bottom_sheet.dart';
import '../services/analytics_service.dart';
import '../models/coupon_model.dart';

class CouponListScreen extends StatefulWidget {
  final String? initialCategory;
  final String? initialSearch;
  final String title;

  const CouponListScreen({
    super.key,
    this.initialCategory,
    this.initialSearch,
    required this.title,
  });

  @override
  State<CouponListScreen> createState() => _CouponListScreenState();
}

class _CouponListScreenState extends State<CouponListScreen> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  String _selectedCategory = 'الكل';
  String _searchQuery = '';
  final ScrollController _scrollController = ScrollController();
  bool _isLoading = false;
  DocumentSnapshot? _lastDocument;
  final List<DocumentSnapshot> _coupons = [];
  String _sortBy = 'usageCount';
  bool _sortAscending = false;
  double _minDiscount = 0;
  double _maxDiscount = 100;

  @override
  void initState() {
    super.initState();
    _selectedCategory = widget.initialCategory ?? 'الكل';
    _searchQuery = widget.initialSearch ?? '';
    _loadCoupons();
    _setupScrollListener();
    AnalyticsService.logScreenView('CouponListScreen');
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
          .orderBy(_sortBy, descending: !_sortAscending)
          .limit(20);

      if (_selectedCategory != 'الكل') {
        query = query.where('category', isEqualTo: _selectedCategory);
      }

      if (_searchQuery.isNotEmpty) {
        query = query
            .where('description', isGreaterThanOrEqualTo: _searchQuery)
            .where('description', isLessThanOrEqualTo: _searchQuery + '\uf8ff');
      }

      query = query.where('discountPercent', isGreaterThanOrEqualTo: _minDiscount);
      query = query.where('discountPercent', isLessThanOrEqualTo: _maxDiscount);

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

  void _showFilterBottomSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => FilterBottomSheet(
        sortBy: _sortBy,
        sortAscending: _sortAscending,
        minDiscount: _minDiscount,
        maxDiscount: _maxDiscount,
        onSortChanged: (sortBy, ascending) {
          setState(() {
            _sortBy = sortBy;
            _sortAscending = ascending;
          });
          _loadCoupons(refresh: true);
        },
        onDiscountRangeChanged: (min, max) {
          setState(() {
            _minDiscount = min;
            _maxDiscount = max;
          });
          _loadCoupons(refresh: true);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        backgroundColor: Theme.of(context).colorScheme.primary,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list),
            onPressed: _showFilterBottomSheet,
          ),
          IconButton(
            icon: Icon(_sortAscending ? Icons.arrow_upward : Icons.arrow_downward),
            onPressed: () {
              setState(() {
                _sortAscending = !_sortAscending;
              });
              _loadCoupons(refresh: true);
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _loadCoupons(refresh: true),
        child: CustomScrollView(
          controller: _scrollController,
          slivers: [
            // Search Bar
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: CustomSearchBar(
                  onSearchChanged: _onSearchChanged,
                  onSearchSubmitted: (query) {
                    _onSearchChanged(query);
                  },
                ),
              ),
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

            // Filter Info
            if (_minDiscount > 0 || _maxDiscount < 100)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16.0),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primaryContainer,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.filter_list,
                          size: 16,
                          color: Theme.of(context).colorScheme.onPrimaryContainer,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'خصم من ${_minDiscount.toInt()}% إلى ${_maxDiscount.toInt()}%',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Theme.of(context).colorScheme.onPrimaryContainer,
                          ),
                        ),
                        const Spacer(),
                        GestureDetector(
                          onTap: () {
                            setState(() {
                              _minDiscount = 0;
                              _maxDiscount = 100;
                            });
                            _loadCoupons(refresh: true);
                          },
                          child: Icon(
                            Icons.clear,
                            size: 16,
                            color: Theme.of(context).colorScheme.onPrimaryContainer,
                          ),
                        ),
                      ],
                    ),
                  ),
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
                      '${_coupons.length} كوبون',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      'ترتيب حسب: ${_getSortLabel()}',
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
    );
  }

  String _getSortLabel() {
    switch (_sortBy) {
      case 'usageCount':
        return 'الأكثر استخداماً';
      case 'discountPercent':
        return 'نسبة الخصم';
      case 'expiresAt':
        return 'تاريخ الانتهاء';
      case 'createdAt':
        return 'الأحدث';
      default:
        return 'الأكثر استخداماً';
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }
}
