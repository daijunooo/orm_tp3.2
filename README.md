# orm_tp3.2

## tp3 对模型关联支持不够友好，此项目可以一定程度的解决

# 如何使用


``` php
//用户表模型
class User extends Model
{
    use ModelTrait;
    
    public function one()
    {
        return $this->find();
    }
    
}
```

> 注意 User 类里面不要有 ModelTrait 中同名方法，如果有请改名。

# 控制器里面调用 User 模型方法 one


``` php

class Index extends Controller
{
    public function index()
    {
        $user = User::_one();
        return $user;
    }
    
    public function getUserById($id)
    {
        $user = User::_find($id);
        return $user;
    }
    
}

```

> 提示：如果 User 模型中有 before_one 方法，那么调用 one 方法前会先调用 before_one。增加了 before 和 after 的生命周期，方便对方法调用前后做一些业务处理。

# 使用模型关联


``` php
//用户表模型
class User extends Model
{
    use ModelTrait;
    
    //一个用户可以购买多个商品，商品表的id和用户表的goods_id关联
    public function goods()
    {
        return $this->hasMany(Goods::class, 'id', 'goods_id');
    }
}

//商品表模型
class Goods extends Model
{
    use ModelTrait;
    
    //一个商品只属于一个用户，用户表的id和商品表的user_id关联
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}



class Index extends Controller
{
    public function myGoods()
    {
        $user = User::_one();
        return $user->goods()->autoData();
    }
    
    public function getUserInfo($goods_id)
    {
        $goods = Goods::_find($goods_id);
        return $goods->user()->data();
    }
    
}


```

# 自动完成


``` php

//用户表模型
class User extends Model
{
    use ModelTrait;
    
    public function getSexAttr()
    {
        $this->sex = $this->sex == 1 ? '男' : '女';
    }
    
    //一个用户可以购买多个商品，商品表的id和用户表的goods_id关联
    public function goods()
    {
        return $this->hasMany(Goods::class, 'id', 'goods_id');
    }
}



class Index extends Controller
{

    public function getUserInfo($goods_id)
    {
        $goods = Goods::_find($goods_id);
        
        //调用autoData会自动调用 get字段名Attr的方法，以方便将字段值转换成前台需要的值
        return $goods->user()->autoData();
    }
    
}

```

> User 模型里面有 getSexAttr 方法，所以会自动将性别转换为男或女。

- 更多用法请看源码，此模型关联查询不会太消耗性能，所有关联查询只会查一次，后面每次都是从缓存中取。
